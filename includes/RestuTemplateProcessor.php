<?php
// PhpWord\TemplateProcessor::setImageValue() cuma bisa nempelin gambar
// INLINE (legacy VML <w:pict>, gak ada opsi wrap/posisi sama sekali - lihat
// vendor/phpoffice/phpword/src/.../TemplateProcessor.php baris ~603). User
// eksplisit minta tanda tangan/paraf gambar pake wrap "In Front of Text" +
// rata tengah horizontal, yang PhpWord versi ini gak expose lewat API
// publik apa pun. Solusinya subclass: semua potongan yang dibutuhin
// (zipClass, tempDocumentRelations/ContentTypes/NewImages/MainPart,
// getNextRelationsIndex(), getMainPartName(), setValueForPart()) ternyata
// `protected` (bukan private) di parent, jadi bisa dipakai langsung dari
// sini tanpa reflection. Cuma method addImageToRelations()/prepareImageAttrs()
// yang `private` - bagian itu diduplikasi minimal di bawah (bukan ubah
// vendor/, itu bakal ketimpa pas composer update).
//
// Kalau upgrade phpoffice/phpword nanti: cek ulang method/property ini
// masih ada & masih protected sebelum asumsikan subclass ini tetap jalan.

declare(strict_types=1);

use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;

class RestuTemplateProcessor extends TemplateProcessor
{
    private static int $drawingIdCounter = 1000; // offset jauh dari id bawaan Word, hindari tabrakan

    /**
     * Ganti macro ${MACRO} di body dokumen (bukan header/footer - TTD/paraf
     * RESTU semua di body) jadi gambar floating "In Front of Text", rata
     * tengah horizontal terhadap kolom, posisi vertikal tetap di baris
     * paragraf yang sama (posOffset 0) - kira-kira nempatin gambar persis
     * di tempat placeholder aslinya, cuma sekarang ngambang di atas teks
     * bukan ikut alur baris.
     *
     * CATATAN: cuma diverifikasi akurat buat paragraf yang HIDUP DI SEL
     * TABEL LEBAR/GAK ber-vMerge (kasus TTD_ATASAN/TTD_BERWENANG/TTD_PEGAWAI
     * di cuti_docx.php - sel VII/VIII hampir selebar halaman). Untuk sel
     * SEMPIT dan/atau vertically-merged (vMerge), 3 pendekatan positioning
     * beda (align=center relativeFrom=column, posOffset absolut
     * relativeFrom=page, layoutInCell 0/1) SEMUA meleset di LibreOffice
     * (gambar ke-lempar ke sel/baris lain) - bug rendering DOCX/LO yg
     * dikenal luas soal anchor di sel ber-vMerge, bukan salah hitung
     * koordinat di sini. Buat kasus begitu, JANGAN pakai method ini - pakai
     * TemplateProcessor::setImageValue() bawaan (inline) sebagai gantinya,
     * lihat cuti_docx_isi_ttd($floating=false) di cuti_docx.php.
     */
    public function setImageValueFloatingCentered(string $macro, string $imgPath, int $widthPx, int $heightPx): void
    {
        $imageData = @getimagesize($imgPath);
        if (!is_array($imageData)) {
            throw new PhpWordException(sprintf('Invalid image: %s', $imgPath));
        }
        $imageMimeType = image_type_to_mime_type($imageData[2]);

        $partFileName = $this->getMainPartName();
        $rid = 'rId' . $this->getNextRelationsIndex($partFileName);
        $this->tambahGambarKeRelations($partFileName, $rid, $imgPath, $imageMimeType);

        $emuPerPx = 9525; // 96dpi, konversi standar OOXML px->EMU
        $cx = $widthPx * $emuPerPx;
        $cy = $heightPx * $emuPerPx;
        $docPrId = self::$drawingIdCounter++;

        $xmlImage = '<w:drawing>'
            . '<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="' . (251658240 + $docPrId) . '" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
            . '<wp:simplePos x="0" y="0"/>'
            . '<wp:positionH relativeFrom="column"><wp:align>center</wp:align></wp:positionH>'
            . '<wp:positionV relativeFrom="paragraph"><wp:posOffset>0</wp:posOffset></wp:positionV>'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:wrapNone/>'
            . '<wp:docPr id="' . $docPrId . '" name="Gambar ' . $docPrId . '"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="' . $docPrId . '" name="Gambar ' . $docPrId . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic>'
            . '</a:graphicData>'
            . '</a:graphic>'
            . '</wp:anchor>'
            . '</w:drawing>';

        // Sama persis pola pencarian tag-boundary yg dipakai
        // TemplateProcessor::setImageValue() bawaan (regex ketemu pasangan
        // tag pembungkus macro, mis. <w:t>${MACRO}</w:t>, dipecah jadi
        // prefix/postfix lalu gambar disisipkan di antaranya) - biar
        // perilakunya konsisten dgn macro gambar yg udah ada.
        $macroFixed = self::ensureMacroCompleted($macro);
        $matches = [];
        if (preg_match('/(<[^<]+>)([^<]*)(' . preg_quote($macroFixed) . ')([^>]*)(<[^>]+>)/Uu', $this->tempDocumentMainPart, $matches)) {
            $wholeTag = $matches[0];
            [$openTag, $prefix, , $postfix, $closeTag] = array_slice($matches, 1);
            $replaceXml = $openTag . $prefix . $closeTag . $xmlImage . $openTag . $postfix . $closeTag;
            $this->tempDocumentMainPart = $this->setValueForPart($wholeTag, $replaceXml, $this->tempDocumentMainPart, self::MAXIMUM_REPLACEMENTS_DEFAULT);
        }
    }

    /**
     * Duplikat minimal dari TemplateProcessor::addImageToRelations() (private
     * di parent, gak bisa dipanggil langsung) - daftarin file gambar ke
     * word/_rels + [Content_Types].xml biar Word ngenalin relationship-nya.
     */
    private function tambahGambarKeRelations(string $partFileName, string $rid, string $imgPath, string $imageMimeType): void
    {
        $extTransform = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/bmp' => 'bmp', 'image/gif' => 'gif'];
        if (!isset($extTransform[$imageMimeType])) {
            throw new PhpWordException("Unsupported image type $imageMimeType");
        }

        if (isset($this->tempDocumentNewImages[$imgPath])) {
            $imgName = $this->tempDocumentNewImages[$imgPath];
        } else {
            $imgExt = $extTransform[$imageMimeType];
            $imgName = 'image_' . $rid . '_' . pathinfo($partFileName, PATHINFO_FILENAME) . '.' . $imgExt;
            $this->zipClass->pclzipAddFile($imgPath, 'word/media/' . $imgName);
            $this->tempDocumentNewImages[$imgPath] = $imgName;
            $xmlImageType = '<Override PartName="/word/media/' . $imgName . '" ContentType="image/' . $imgExt . '"/>';
            $this->tempDocumentContentTypes = str_replace('</Types>', $xmlImageType, $this->tempDocumentContentTypes) . '</Types>';
        }

        $xmlImageRelation = '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $imgName . '"/>';

        if (!isset($this->tempDocumentRelations[$partFileName])) {
            $this->tempDocumentRelations[$partFileName] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
            $xmlRelationsType = '<Override PartName="/' . $this->getRelationsName($partFileName) . '" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
            $this->tempDocumentContentTypes = str_replace('</Types>', $xmlRelationsType, $this->tempDocumentContentTypes) . '</Types>';
        }
        $this->tempDocumentRelations[$partFileName] = str_replace('</Relationships>', $xmlImageRelation, $this->tempDocumentRelations[$partFileName]) . '</Relationships>';
    }
}
