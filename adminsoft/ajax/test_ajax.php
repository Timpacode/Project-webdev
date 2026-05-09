<?php
// test_ajax.php - Test if AJAX endpoint is reachable
$url = 'http://' . $_SERVER['HTTP_HOST'] . '/AdminSoft/adminsoft/ajax/generate_document_ajax.php';
echo "Testing: $url<br>";

// Test with curl or file_get_contents
$response = @file_get_contents($url);
if ($response === FALSE) {
    echo "❌ Cannot reach AJAX endpoint<br>";
    echo "Error: " . error_get_last()['message'];
} else {
    echo "✅ AJAX endpoint is reachable<br>";
    echo "Response: " . $response;
}
?>