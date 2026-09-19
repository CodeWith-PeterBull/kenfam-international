@php($brandPrimary = config('kenfam.colors.primary', '#6a753d'))
@php($brandOnPrimary = config('kenfam.colors.on_primary', '#ffffff'))
<style>
    @page { margin: {{ $orientation === 'landscape' ? '20mm 16mm 18mm' : '18mm 16mm 20mm' }}; size: A4 {{ $orientation }}; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #1d1d25; font-family: "DejaVu Sans", sans-serif; font-size: {{ $orientation === 'landscape' ? '8px' : '9px' }}; line-height: 1.4; }
    .report-header { padding-bottom: 10px; margin-bottom: 14px; border-bottom: 2px solid {{ $brandPrimary }}; text-align: center; }
    .report-logo { max-width: 170px; max-height: {{ $orientation === 'landscape' ? '42px' : '52px' }}; margin-bottom: 7px; }
    .institution-name { margin: 0; font-size: {{ $orientation === 'landscape' ? '15px' : '18px' }}; font-weight: 700; }
    .institution-descriptor { margin-top: 2px; color: #6d7079; font-size: 9px; }
    .institution-contact { margin-top: 6px; color: {{ $brandPrimary }}; font-size: 8px; }
    .report-title { margin: 14px 0 3px; text-align: center; font-size: {{ $orientation === 'landscape' ? '15px' : '17px' }}; font-weight: 700; }
    .report-subtitle { margin: 0 0 14px; color: #6d7079; text-align: center; font-size: 9px; }
    .report-section { margin-bottom: 16px; page-break-inside: avoid; }
    .section-title { padding-bottom: 4px; margin: 0 0 8px; border-bottom: 1px solid #b7bac1; font-size: 11px; font-weight: 700; }
    .metrics { display: table; width: 100%; table-layout: fixed; margin-bottom: 12px; }
    .metric { display: table-cell; padding: 7px; border: 1px solid #cfd2d8; text-align: center; }
    .metric strong { display: block; color: {{ $brandPrimary }}; font-size: 15px; }
    .filters { padding: 6px 8px; margin-bottom: 10px; border: 1px solid #cfd2d8; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { padding: {{ $orientation === 'landscape' ? '4px 5px' : '5px 6px' }}; border: 1px solid #b7bac1; text-align: left; vertical-align: top; overflow-wrap: break-word; }
    th { color: {{ $brandOnPrimary }}; background: {{ $brandPrimary }}; font-weight: 700; }
    tr { page-break-inside: avoid; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .amount { white-space: nowrap; }
    .amount-strong { font-weight: 700; white-space: nowrap; }
    .amount-was { color: #8a8d95; text-decoration: line-through; white-space: nowrap; }
    .muted { color: #8a8d95; }
    .report-note { padding: 6px 8px; margin-bottom: 10px; border: 1px solid #e0c98a; background: #fbf4e0; color: #7a5c16; }
    .report-footer { position: fixed; right: 0; bottom: -10mm; left: 0; padding-top: 5px; border-top: 1px solid #cfd2d8; color: #6d7079; font-size: 7px; text-align: center; }
    .page-number::before { content: counter(page); }
</style>

