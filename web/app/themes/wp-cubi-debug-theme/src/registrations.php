<?php

namespace Globalis\WP\Test;

use Exception;

define('REGISTRATION_ACF_KEY_LAST_NAME', 'field_64749cfff238e');
define('REGISTRATION_ACF_KEY_FIRST_NAME', 'field_64749d4bf238f');

add_filter('wp_insert_post_data', __NAMESPACE__ . '\\save_auto_title', 99, 2);
add_action('edit_form_after_title', __NAMESPACE__ . '\\display_custom_title_field');
add_action('save_post', __NAMESPACE__ . '\\send_registration_email', 10, 3);

/**
 * @param $data
 * @param $postarr
 * @return mixed
 */
function save_auto_title($data, $postarr): mixed
{
    if (
            $data['post_type'] !== 'registrations' ||
            $data['post_status'] === 'auto-draft'
    ) {
        return $data;
    }

    if (!isset($postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME]) || !isset($postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME])) {
        return $data;
    }

    $data['post_title'] = "#" . $postarr['ID'] .  " (" . $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME] . " " . $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME] . ")";

    $data['post_name']  = wp_unique_post_slug(sanitize_title(str_replace('/', '-', $data['post_title'])), $postarr['ID'], $postarr['post_status'], $postarr['post_type'], $postarr['post_parent']);

    return $data;
}

/**
 * @param \WP_Post $post
 * @return void
 */
function display_custom_title_field(\WP_Post $post): void
{
    if ($post->post_type !== 'registrations' || $post->post_status === 'auto-draft') {
        return;
    }
    ?>
    <h1><?= $post->post_title ?></h1>
    <?php
}

/**
 * @param int $post_id
 * @param \WP_Post $post
 * @param bool $update
 * @return void
 * @throws Exception
 */
function send_registration_email(int $post_id, \WP_Post $post, bool $update): void
{
    if ($post->post_type !== 'registrations') {
        return;
    }

    /*
     * We generate the tickets even for each time we update the user
     * We could change that and generate the PDF only when the user is created
     * Using the update boolean
     */

    // Retrieve all needed information to generate PDF
    $first_name = get_post_meta($post_id, 'registration_first_name', true);
    $last_name = get_post_meta($post_id, 'registration_last_name', true);
    $email = get_post_meta($post_id, 'registration_email', true);
    $eventId = get_post_meta($post_id, 'registration_event_id', true);
    $eventName = get_post_field('post_title', $eventId);
    $ticketUrl = 'https://ticketto-' . strtolower($eventName) . '-' . $post->ID;

    // Generate PDF
    $pdfDatas = [
        'firstName' => $first_name,
        'lastName' => $last_name,
        'eventName' => $eventName,
        'ticketUrl' => $ticketUrl
    ];

    send_registration_email_with_pdf($email, $pdfDatas);
}

/**
 * @param string $pdfDatas
 * @return mixed
 * @throws Exception
 */
function generate_pdf(string $pdfDatas): mixed
{
    // Using the ApiTemplate api to generate the PDF with a custom template
    /*
     * I could have used the PDF saved in the Even custom Type
     * But I think it was interesting to generate a custom one through an api
     */
    $url = "https://rest.apitemplate.io/v2/create-pdf?template_id=" . PDF_TEMPLATE_ID;
    $headers = ["X-API-KEY: " . PDF_API_KEY];

    $curl = curl_init();
    if ($pdfDatas) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pdfDatas);
    }
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);
    if (!$result) {
        return null;
    } else {
        $json_result = json_decode($result, 1);
        if ($json_result["status"] == "success") {
            return $json_result;
        } else {
            throw new Exception("Something went wrong while generating the pdf");
        }
    }
}

/**
 * @throws Exception
 */
function send_registration_email_with_pdf(string $to_email, array $pdfDatas): void
{
    $pdfInfos = generate_pdf(json_encode($pdfDatas));

    if ($pdfInfos) {
        // Download and save the generated PDF
        $DownloadPath = wp_get_upload_dir();
        $pathToSavedPdf = download_file($pdfInfos["download_url"], $DownloadPath['path'] . '/' . $pdfInfos['transaction_ref'] . '.pdf');

        // Get the mail ready
        $subject = 'Billets pour votre évènement';
        $message = 'Bonjour' . $pdfDatas['firstName'] . ', <br>';
        $message .= 'Merci pour votre isncription à l\'évènement : ' . $pdfDatas['eventName'] . '<br>';
        $message .= 'Veuillez trouver ci-joint vos billets';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Add the attached PDF
        $attachments = [$pathToSavedPdf];

        // Send the mail
        wp_mail($to_email, $subject, $message, $headers, $attachments);
    } else {
        throw new Exception("Something went wrong while sending the email");
    }
}

/**
 * @param string $url
 * @param string $save_to
 * @return string
 * @throws Exception
 */
function download_file(string $url, string $save_to): string
{
    $file_content = file_get_contents($url);

    // Check if download is a success
    if ($file_content === false) {
        throw new Exception("Failed to download file from $url");
    }

    try {
        // Save the file in a given directory
        $result = file_put_contents($save_to, $file_content);
    } catch (Exception $e) {
        throw new Exception("Error while saving file to $save_to // Error: $e");
    }

    // Check if save has failed
    if ($result === false) {
        throw new Exception("Failed to save file to $save_to");
    }

    return $save_to;
}
