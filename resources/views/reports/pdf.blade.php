<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport {{ $report['farm']->name }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10px; line-height: 1.45; }
        h1 { margin: 0; font-size: 20px; color: #065f46; }
        h2 { margin: 22px 0 8px; font-size: 13px; color: #065f46; border-bottom: 1px solid #a7f3d0; padding-bottom: 4px; }
        h3 { margin: 12px 0 5px; font-size: 11px; color: #334155; }
        p { margin: 0 0 6px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #059669; padding-bottom: 10px; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .header .meta { text-align: right; font-size: 9px; color: #475569; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
        .kpis td { width: 25%; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 4px; padding: 8px; }
        .kpis .label { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #047857; }
        .kpis .value { display: block; margin-top: 3px; font-size: 14px; font-weight: bold; color: #064e3b; }
        .kpis .hint { display: block; margin-top: 2px; font-size: 8px; color: #475569; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th { background: #f0fdf4; color: #065f46; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        table.data th, table.data td { border-bottom: 1px solid #e2e8f0; padding: 4px 5px; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .tag { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #e2e8f0; color: #334155; font-size: 8px; }
        .tag--critical { background: #fee2e2; color: #991b1b; }
        .tag--high { background: #ffedd5; color: #9a3412; }
        .two { width: 100%; border-collapse: collapse; }
        .two td { width: 50%; vertical-align: top; padding-right: 12px; }
        .footer { margin-top: 26px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 8px; color: #94a3b8; text-align: center; }
        .notice { margin-top: 8px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; padding: 7px 9px; font-size: 9px; }
        .empty { color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <h1>{{ $report['farm']->name }}</h1>
                    <p class="muted">Rapport d’exploitation agricole</p>
                </td>
                <td class="meta">
                    <p>Période : du <strong>{{ $report['period']['from']->format('d/m/Y') }}</strong>
                        au <strong>{{ $report['period']['to']->format('d/m/Y') }}</strong></p>
                    <p>Édité le {{ now()->format('d/m/Y à H:i') }}</p>
                    @if ($report['campaign_id'])
                        <p>Campagne filtrée : #{{ $report['campaign_id'] }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="kpis">
        <tr>
            <td>
                <span class="label">Campagnes</span>
                <span class="value">{{ $report['summary']['campaigns'] }}</span>
                <span class="hint">{{ $report['summary']['active_campaigns'] }} active(s)</span>
            </td>
            <td>
                <span class="label">Surface cultivée</span>
                <span class="value">{{ number_format((float) $report['summary']['cultivated_area_m2'], 0, ',', ' ') }} m²</span>
                <span class="hint">Sur les campagnes de la période</span>
            </td>
            <td>
                <span class="label">Récoltes</span>
                <span class="value">{{ number_format((float) $report['summary']['harvested_quantity'], 1, ',', ' ') }}</span>
                <span class="hint">{{ $report['summary']['harvest_count'] }} saisie(s)</span>
            </td>
            <td>
                <span class="label">Problèmes</span>
                <span class="value">{{ $report['summary']['issues'] }}</span>
                <span class="hint">{{ $report['summary']['open_issues'] }} non résolu(s) · {{ $report['summary']['critical_issues'] }} critique(s)</span>
            </td>
        </tr>
    </table>

    @unless ($canViewFinances)
        <div class="notice">
            Les montants financiers ne sont pas inclus dans ce rapport : le rôle de l’utilisateur ne permet pas d’y accéder.
        </div>
    @endunless

    @if ($canViewFinances)
        <h2>Synthèse financière</h2>
        <table class="kpis">
            <tr>
                <td>
                    <span class="label">Dépenses</span>
                    <span class="value">{{ number_format((float) $report['summary']['expenses'], 0, ',', ' ') }} Ar</span>
                </td>
                <td>
                    <span class="label">Recettes</span>
                    <span class="value">{{ number_format((float) $report['summary']['revenues'], 0, ',', ' ') }} Ar</span>
                </td>
                <td>
                    <span class="label">Marge simplifiée</span>
                    <span class="value">{{ number_format((float) $report['summary']['margin'], 0, ',', ' ') }} Ar</span>
                </td>
                <td>
                    <span class="label">Reste à encaisser</span>
                    <span class="value">{{ number_format((float) $report['summary']['sales_outstanding'], 0, ',', ' ') }} Ar</span>
                    <span class="hint">{{ number_format((float) $report['summary']['sales'], 0, ',', ' ') }} Ar de ventes</span>
                </td>
            </tr>
        </table>
    @endif

    <h2>Campagnes</h2>
    @if ($report['campaigns']->isEmpty())
        <p class="empty">Aucune campagne sur la période.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Campagne</th>
                    <th>Culture</th>
                    <th>Parcelle / zone</th>
                    <th>Période</th>
                    <th class="right">Surface</th>
                    <th>Statut</th>
                    <th>Suivi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['campaigns'] as $campaign)
                    <tr>
                        <td><strong>{{ $campaign->name }}</strong></td>
                        <td>{{ $campaign->crop?->name ?? '—' }}</td>
                        <td>{{ $campaign->productionUnit?->code ?? '—' }}@if ($campaign->zone) / {{ $campaign->zone->code }}@endif</td>
                        <td>{{ $campaign->start_date?->format('d/m/Y') }} → {{ $campaign->expected_end_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $campaign->cultivated_area_m2, 0, ',', ' ') }} m²</td>
                        <td>{{ $campaign->status->label() }}</td>
                        <td>{{ $campaign->activities_count }} act. · {{ $campaign->treatments_count }} tr. · {{ $campaign->harvests_count }} réc. · {{ $campaign->issues_count }} pb.</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Problèmes agricoles</h2>
    @if ($report['issues']->isEmpty())
        <p class="empty">Aucun problème signalé sur la période.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Problème</th>
                    <th>Type</th>
                    <th>Gravité</th>
                    <th>Statut</th>
                    <th>Parcelle</th>
                    <th class="right">Surface affectée</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['issues'] as $issue)
                    <tr>
                        <td>{{ $issue->reference }}</td>
                        <td>{{ $issue->observed_at?->format('d/m/Y') }}</td>
                        <td>{{ $issue->title }}</td>
                        <td>{{ $issue->type->label() }}</td>
                        <td><span class="tag {{ $issue->severity->value === 'critical' ? 'tag--critical' : ($issue->severity->value === 'high' ? 'tag--high' : '') }}">{{ $issue->severity->label() }}</span></td>
                        <td>{{ $issue->status->label() }}</td>
                        <td>{{ $issue->productionUnit?->code ?? '—' }}</td>
                        <td class="right">{{ $issue->affected_area_m2 !== null ? number_format((float) $issue->affected_area_m2, 0, ',', ' ').' m²' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="two">
        <tr>
            <td>
                <h2>Récoltes par unité</h2>
                @if (count($report['harvests_by_unit']) === 0)
                    <p class="empty">Aucune récolte.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Unité</th><th class="right">Quantité</th><th class="right">Saisies</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($report['harvests_by_unit'] as $row)
                                <tr>
                                    <td>{{ $row['unit'] }}</td>
                                    <td class="right">{{ number_format((float) $row['quantity'], 2, ',', ' ') }}</td>
                                    <td class="right">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td>
                <h2>Traitements</h2>
                @if ($report['treatments']->isEmpty())
                    <p class="empty">Aucun traitement.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Produit</th><th>Date</th><th>Récolte ≥</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($report['treatments'] as $treatment)
                                <tr>
                                    <td>{{ $treatment->input?->name ?? '—' }}</td>
                                    <td>{{ $treatment->applied_at?->format('d/m/Y') }}</td>
                                    <td>{{ $treatment->minimum_harvest_at?->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    @if ($canViewFinances)
        <h2>Journal financier</h2>
        @if ($report['expenses']->isEmpty() && $report['revenues']->isEmpty())
            <p class="empty">Aucune écriture financière sur la période.</p>
        @else
            <table class="data">
                <thead>
                    <tr><th>Nature</th><th>Date</th><th>Catégorie</th><th>Libellé</th><th class="right">Montant</th></tr>
                </thead>
                <tbody>
                    @foreach ($report['expenses'] as $expense)
                        <tr>
                            <td>Dépense</td>
                            <td>{{ $expense->expense_date?->format('d/m/Y') }}</td>
                            <td>{{ $expense->category?->label() }}</td>
                            <td>{{ $expense->description }}</td>
                            <td class="right">-{{ number_format((float) $expense->amount, 0, ',', ' ') }} Ar</td>
                        </tr>
                    @endforeach
                    @foreach ($report['revenues'] as $revenue)
                        <tr>
                            <td>Recette</td>
                            <td>{{ $revenue->revenue_date?->format('d/m/Y') }}</td>
                            <td>{{ $revenue->category?->label() }}</td>
                            <td>{{ $revenue->description }}</td>
                            <td class="right">{{ number_format((float) $revenue->amount, 0, ',', ' ') }} Ar</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <div class="footer">
        VolyLegume — rapport généré automatiquement à partir des données de l’exploitation {{ $report['farm']->name }}.
        La progression culturale et les indicateurs reposent uniquement sur les événements et écritures enregistrés.
    </div>
</body>
</html>
