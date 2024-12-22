<?php
// Display all errors for debugging during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'dbconnection.php';

header("Content-Type: application/json");

// Acknowledge receipt of the M-Pesa response
$response = json_encode([
  "ResultCode" => 0,
  "ResultDesc" => "Confirmation Received Successfully"
]);

// Read the M-Pesa response from the request body
$mpesaResponse = file_get_contents('php://input');

// Log the response for debugging and record-keeping
$logFile = "M_PESAConfirmationResponse.json";
file_put_contents($logFile, $mpesaResponse . PHP_EOL, FILE_APPEND);

// Send acknowledgment response
echo $response;

// Decode the JSON response
$data = json_decode($mpesaResponse);

// Extract common fields
$MerchantRequestID = $data->Body->stkCallback->MerchantRequestID ?? null;
$CheckoutRequestID = $data->Body->stkCallback->CheckoutRequestID ?? null;
$ResultCode = $data->Body->stkCallback->ResultCode ?? null;
$ResultDesc = $data->Body->stkCallback->ResultDesc ?? null;

// Initialize optional fields for successful transactions
$Amount = null;
$TransactionId = null;
$PhoneNumber = null;

// Handle various response scenarios
if ($ResultCode == 0 && isset($data->Body->stkCallback->CallbackMetadata->Item)) {
  // Extract additional metadata for successful transactions
  foreach ($data->Body->stkCallback->CallbackMetadata->Item as $item) {
    switch ($item->Name) {
      case "Amount":
        $Amount = $item->Value;
        break;
      case "MpesaReceiptNumber":
        $TransactionId = $item->Value;
        break;
      case "PhoneNumber":
        $PhoneNumber = $item->Value;
        break;
    }
  }

  // Query to insert successful transaction
  $query = "INSERT INTO transactions 
              (MerchantRequestID, CheckoutRequestID, ResultCode, ResultDesc, Amount, MpesaReceiptNumber, PhoneNumber) 
              VALUES 
              ('$MerchantRequestID', '$CheckoutRequestID', '$ResultCode', '$ResultDesc', '$Amount', '$TransactionId', '$PhoneNumber')";
} elseif ($ResultCode == 1032) {
  // Query to handle cancelled transactions
  $query = "INSERT INTO transactions 
              (MerchantRequestID, CheckoutRequestID, ResultCode, ResultDesc) 
              VALUES 
              ('$MerchantRequestID', '$CheckoutRequestID', '$ResultCode', 'Request cancelled by user')";
} elseif ($ResultCode == 1037) {
  // Query to handle timeout transactions
  $query = "INSERT INTO transactions 
              (MerchantRequestID, CheckoutRequestID, ResultCode, ResultDesc) 
              VALUES 
              ('$MerchantRequestID', '$CheckoutRequestID', '$ResultCode', 'Timeout: User cannot be reached')";
} else {
  // Query to handle other unknown scenarios
  $query = "INSERT INTO transactions 
              (MerchantRequestID, CheckoutRequestID, ResultCode, ResultDesc) 
              VALUES 
              ('$MerchantRequestID', '$CheckoutRequestID', '$ResultCode', '$ResultDesc')";
}

// Execute the query and handle errors
if (mysqli_query($db, $query)) {
  error_log("Transaction saved successfully: $MerchantRequestID\n", 3, $logFile);
} else {
  error_log("Error saving transaction: " . mysqli_error($db) . "\n", 3, $logFile);
}
