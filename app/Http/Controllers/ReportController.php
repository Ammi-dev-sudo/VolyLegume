<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Expense;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request, ReportService $service): View
    {
        Gate::authorize('viewAny', Campaign::class);

        [$from, $to, $campaignId] = $this->filters($request);
        $canViewFinances = Gate::allows('viewAny', Expense::class);

        $report = $service->build($this->farm(), $from, $to, $campaignId, $canViewFinances);

        return view('reports.index', [
            'report' => $report,
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(['id', 'name']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'campaign_id' => $campaignId,
            ],
            'canViewFinances' => $canViewFinances,
        ]);
    }

    public function pdf(Request $request, ReportService $service): Response
    {
        Gate::authorize('viewAny', Campaign::class);

        [$from, $to, $campaignId] = $this->filters($request);
        $canViewFinances = Gate::allows('viewAny', Expense::class);

        $report = $service->build($this->farm(), $from, $to, $campaignId, $canViewFinances);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'canViewFinances' => $canViewFinances,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->filename($report, 'pdf'));
    }

    public function excel(Request $request, ReportService $service): StreamedResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        [$from, $to, $campaignId] = $this->filters($request);
        $canViewFinances = Gate::allows('viewAny', Expense::class);

        $report = $service->build($this->farm(), $from, $to, $campaignId, $canViewFinances);
        $sheets = $service->sheets($report);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            if ($title === 'Finances' && ! $canViewFinances) {
                continue;
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($rows, null, 'A1');

            $highestColumn = $sheet->getHighestColumn();
            $highestRow = max(1, $sheet->getHighestRow());

            $sheet->getStyle('A1:'.$highestColumn.'1')->getFont()->setBold(true);
            $sheet->getStyle('A1:'.$highestColumn.'1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D1FAE5');
            $sheet->getStyle('A1:'.$highestColumn.'1')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            foreach (range('A', $highestColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $sheet->freezePane('A2');
            $sheet->getStyle('A1:'.$highestColumn.$highestRow)
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $this->filename($report, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: ?int}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('farm_id', $this->farmId())],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, isset($validated['campaign_id']) ? (int) $validated['campaign_id'] : null];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function filename(array $report, string $extension): string
    {
        $farm = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $report['farm']->name) ?: 'exploitation';

        return sprintf(
            'rapport-%s-%s-%s.%s',
            trim($farm, '-'),
            $report['period']['from']->format('Ymd'),
            $report['period']['to']->format('Ymd'),
            $extension,
        );
    }
}
