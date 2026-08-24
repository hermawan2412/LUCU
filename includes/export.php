<?php
// Export CSV. MACOA "export excel" sebenarnya HTML mentah dikasih header
// application/vnd-ms-excel biar Excel mau buka (Excel emang bisa buka
// gitu, tapi itu bukan format yg jujur - mismatch antara header sama isi
// beneran). Di sini CSV asli: Content-Type bener, filename .csv jujur,
// Excel/Sheets/LibreOffice semua baca native tanpa "trik".

declare(strict_types=1);

function csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM - Excel Windows perlu ini biar UTF-8 kebaca bener
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
