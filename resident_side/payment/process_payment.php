<?php
   require_once 'process_payment.php';
   
   $testDetails = [
       'invoice_number' => 'TEST123',
       'category' => 'Test Payment',
       'payment_date' => date('Y-m-d'),
       'payment_method' => 'bank',
       'amount_paid' => 100,
       'reference_number' => 'REF123',
       'total_amount' => 100,
       'total_paid' => 100,
       'remaining_balance' => 0,
       'payment_status' => 'paid'
   ];
   
   $result = sendPaymentReceipt('your-test-email@gmail.com', 'Test User', $testDetails);
   echo $result ? 'Success!' : 'Failed!';