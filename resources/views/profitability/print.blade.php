<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rentabilité — {{ $farm->name }}</title>
    <style>
        :root {
            color-scheme: light;
            --canvas: #f3f1e9;
            --surface: #fffefa;
            --ink: #1d293b;
            --muted: #737d8b;
            --line: #e4e0d6;
            --accent: #d65b3b;
            --accent-dark: #ad452d;
        }
        * { box-sizing: border-box; }
        body { max-width: 1440px; margin: 0 auto; padding: 32px; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--ink); background: var(--canvas); }
        h1 { margin: 0 0 4px; font-family: Georgia, "Times New Roman", serif; font-size: 28px; letter-spacing: -0.035em; }
        h2 { margin: 28px 0 8px; border-bottom: 1px solid var(--line); padding-bottom: 7px; color: var(--ink); font-size: 16px; }
        p { margin: 4px 0; color: var(--muted); font-size: 12px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); font-size: 11px; box-shadow: 0 8px 26px rgb(29 41 59 / 0.05); }
        th, td { border-bottom: 1px solid var(--line); padding: 8px 9px; text-align: left; }
        th { background: #eeece3; text-transform: uppercase; font-size: 9px; letter-spacing: 0.07em; color: #465166; }
        tbody tr:last-child td { border-bottom: 0; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .positive { color: #16835d; font-weight: 600; }
        .negative { color: #dc2626; font-weight: 600; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 20px; }
        .toolbar button, .toolbar a { border: 1px solid var(--line); background: var(--surface); border-radius: 9px; padding: 9px 14px; font-size: 12px; cursor: pointer; text-decoration: none; color: var(--ink); transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease; }
        .toolbar button:hover, .toolbar a:hover { transform: translateY(-2px); box-shadow: 0 7px 18px rgb(29 41 59 / 0.12); }
        .toolbar button { background: var(--accent); border-color: var(--accent); color: #fffefa; }
        .totals { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; padding: 14px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); box-shadow: 0 8px 26px rgb(29 41 59 / 0.05); }
        .totals div { min-width: 140px; }
        .totals span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .totals strong { font-size: 15px; }
        .empty { color: var(--muted); font-style: italic; }
        @page { size: A4 landscape; margin: 12mm; }
        @media (max-width: 700px) {
            body { padding: 18px; }
            table { display: block; overflow-x: auto; }
        }
        @media print {
            body { max-width: none; padding: 0; background: #fff; }
            .toolbar { display: none; }
            table { display: table; overflow: visible; box-shadow: none; }
            .totals { box-shadow: none; }
            h2 { break-after: avoid; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    @php
        $money = fn ($value): string => number_format((float) $value, 0, ',', ' ').' Ar';
        $number = fn ($value, int $decimals = 1): string => $value === null ? '—' : number_format((float) $value, $decimals, ',', ' ');
        $marginClass = fn ($value): string => (float) $value >= 0 ? 'positive' : 'negative';
    @endphp

    <div class="toolbar">
        <a href="{{ route('profitability.index', request()->query()) }}">Retour à l’écran</a>
        <button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    </div>

    <h1>Rentabilité — {{ $farm->name }}</h1>
    <p>
        Édité le {{ now()->format('d/m/Y à H:i') }}
        @if ($data['period']['from'] || $data['period']['to'])
            · Période : {{ $data['period']['from'] ? \Illuminate\Support\Carbon::parse($data['period']['from'])->format('d/m/Y') : 'début' }}
            → {{ $data['period']['to'] ? \Illuminate\Support\Carbon::parse($data['period']['to'])->format('d/m/Y') : 'aujourd’hui' }}
        @endif
        · Les montants sont exprimés en ariary (Ar).
    </p>

    <div class="totals">
        <div><span>Campagnes</span><strong>{{ $data['totals']['campaigns'] }}</strong></div>
        <div><span>Surface cultivée</span><strong>{{ $number($data['totals']['area_m2'], 0) }} m²</strong></div>
        <div><span>Dépenses</span><strong>{{ $money($data['totals']['expenses']) }}</strong></div>
        <div><span>Recettes</span><strong>{{ $money($data['totals']['revenues']) }}</strong></div>
        <div><span>Marge</span><strong class="{{ $marginClass($data['totals']['margin']) }}">{{ $money($data['totals']['margin']) }}</strong></div>
        <div><span>Récolte</span><strong>{{ $number($data['totals']['harvest_kg']) }} kg</strong></div>
        <div><span>Eau / kg</span><strong>{{ $data['totals']['water_per_kg'] !== null ? $number($data['totals']['water_per_kg'], 3).' m³' : '—' }}</strong></div>
    </div>

    <h2>Par culture</h2>
    @if (count($data['crops']) === 0)
        <p class="empty">Aucune donnée.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Culture</th>
                    <th class="num">Campagnes</th>
                    <th class="num">Surface</th>
                    <th class="num">Dépenses</th>
                    <th class="num">Recettes</th>
                    <th class="num">Marge</th>
                    <th class="num">Marge / m²</th>
                    <th class="num">Récolte</th>
                    <th class="num">Rendement</th>
                    <th class="num">Eau / kg</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['crops'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['campaigns'] }}</td>
                        <td class="num">{{ $number($row['area_m2'], 0) }} m²</td>
                        <td class="num">{{ $money($row['expenses']) }}</td>
                        <td class="num">{{ $money($row['revenues']) }}</td>
                        <td class="num {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                        <td class="num">{{ $money($row['margin_per_m2'] ?? 0) }}</td>
                        <td class="num">{{ $number($row['harvest_kg']) }} kg</td>
                        <td class="num">{{ $row['yield_kg_per_m2'] !== null ? $number($row['yield_kg_per_m2'], 2).' kg/m²' : '—' }}</td>
                        <td class="num">{{ $row['water_per_kg'] !== null ? $number($row['water_per_kg'], 3).' m³' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Par parcelle</h2>
    @if (count($data['parcels']) === 0)
        <p class="empty">Aucune donnée.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Parcelle</th>
                    <th class="num">Campagnes</th>
                    <th class="num">Surface</th>
                    <th class="num">Dépenses</th>
                    <th class="num">Recettes</th>
                    <th class="num">Marge</th>
                    <th class="num">Marge / m²</th>
                    <th class="num">Récolte</th>
                    <th class="num">Rendement</th>
                    <th class="num">Eau / kg</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['parcels'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['campaigns'] }}</td>
                        <td class="num">{{ $number($row['area_m2'], 0) }} m²</td>
                        <td class="num">{{ $money($row['expenses']) }}</td>
                        <td class="num">{{ $money($row['revenues']) }}</td>
                        <td class="num {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                        <td class="num">{{ $money($row['margin_per_m2'] ?? 0) }}</td>
                        <td class="num">{{ $number($row['harvest_kg']) }} kg</td>
                        <td class="num">{{ $row['yield_kg_per_m2'] !== null ? $number($row['yield_kg_per_m2'], 2).' kg/m²' : '—' }}</td>
                        <td class="num">{{ $row['water_per_kg'] !== null ? $number($row['water_per_kg'], 3).' m³' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Détail par campagne</h2>
    @if (count($data['campaigns']) === 0)
        <p class="empty">Aucune donnée.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Campagne</th>
                    <th>Culture</th>
                    <th>Parcelle</th>
                    <th>Début</th>
                    <th>Statut</th>
                    <th class="num">Dépenses</th>
                    <th class="num">Recettes</th>
                    <th class="num">Marge</th>
                    <th class="num">Récolte</th>
                    <th class="num">Eau</th>
                    <th class="num">Budget consommé</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['campaigns'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['crop'] }}</td>
                        <td>{{ $row['parcel'] }}</td>
                        <td>{{ $row['start_date'] ?? '—' }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td class="num">{{ $money($row['expenses']) }}</td>
                        <td class="num">{{ $money($row['revenues']) }}</td>
                        <td class="num {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                        <td class="num">{{ $number($row['harvest_kg']) }} kg</td>
                        <td class="num">{{ $number($row['water_m3'], 1) }} m³</td>
                        <td class="num">{{ $row['budget_usage_percent'] !== null ? $number($row['budget_usage_percent'], 1).' %' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
