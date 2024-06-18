<?php

namespace Globalis\WP\Test;

use DateTime;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

add_action('generate_download_export_url', __NAMESPACE__ . '\\get_download_url', 10, 2);

/**
 * @throws WriterNotOpenedException
 * @throws IOException
 * @throws InvalidArgumentException
 */
function get_download_url(array $registrations, string $post_title): string
{
    /** Configure the file */
    $date = new DateTime('now');
    $directoryPath = wp_get_upload_dir();

    $fileName = 'export-' . $post_title . '-' . $date->format('Y-m') . '.xlsx';
    $filePath = $directoryPath['path'] . '/' . $fileName;
    $downloadUrl = $directoryPath['url'] . '/' . $fileName;

    /** Check if export exist for current month and delete it if so */
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    $writer = new Writer();
    $writer->openToFile($filePath);
    $writer->setCreator('RH Globalis');

    $sheetView = new SheetView();
    $sheetView->setFreezeRow(2); // First row will be fixed
    $writer->getCurrentSheet()->setSheetView($sheetView);

    /** Prepare the head row */
    $formatXlsCells = [
        'headCells' => [
            Cell::fromValue('firstName'),
            Cell::fromValue('LastName'),
            Cell::fromValue('Email'),
            Cell::fromValue('Phone number'),
        ],
    ];

    /** Prepare all rows */
    foreach ($registrations as $registration) {
        $phone = $registration['phone'] ?: 'no phone';
        $formatXlsCells[] = [
            Cell::fromValue($registration['firstName']),
            Cell::fromValue($registration['lastName']),
            Cell::fromValue($registration['email']),
            Cell::fromValue($phone),
        ];
    }

    /** Creating all rows */
    $multipleRows = [];
    foreach ($formatXlsCells as $cell) {
        $multipleRows[] = new Row($cell);
    }

    /** Add multiple rows at a time */
    $writer->addRows($multipleRows);
    $writer->close();

    return $downloadUrl;
}
