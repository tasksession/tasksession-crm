<?php
// ajax/currency-converter.php
header('Content-Type: application/json; charset=UTF-8');

$currencies = [
  'USD'=>'🇺🇸 USD','EUR'=>'🇪🇺 EUR','GBP'=>'🇬🇧 GBP',
  'PKR'=>'🇵🇰 PKR','INR'=>'🇮🇳 INR','CAD'=>'🇨🇦 CAD',
  'AUD'=>'🇦🇺 AUD','AED'=>'🇦🇪 AED','SAR'=>'🇸🇦 SAR',
  'CNY'=>'🇨🇳 CNY','JPY'=>'🇯🇵 JPY','TRY'=>'🇹🇷 TRY',
  'RUB'=>'🇷🇺 RUB','ZAR'=>'🇿🇦 ZAR',
];

// Prep response
$response = [
  'success'     => false,
  'converted'   => null,
  'error'       => 'Invalid request',
  'from'        => null,
  'to'          => null,
  'amount'      => null,
  'last_update' => null,
];

// Validate POST
if ($_SERVER['REQUEST_METHOD']==='POST'
  && isset($_POST['amount'], $_POST['from'], $_POST['to'])
) {
  $amt  = floatval($_POST['amount']);
  $from = strtoupper(trim($_POST['from']));
  $to   = strtoupper(trim($_POST['to']));

  $response['from']   = $from;
  $response['to']     = $to;
  $response['amount'] = number_format($amt,2,'.','');

  if ($amt>0 && isset($currencies[$from], $currencies[$to])) {
    $url = "https://open.er-api.com/v6/latest/".urlencode($from);
    $ch  = curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT       =>5,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw!==false && $code===200) {
      $data = json_decode($raw,true);
      if (!empty($data['rates'][$to])) {
        $rate     = $data['rates'][$to];
        $conv     = $rate * $amt;
        $utc      = $data['time_last_update_utc'] ?? '';
        $lastUpd  = $utc
          ? date('d/m/Y g:i A', strtotime($utc))
          : date('d/m/Y g:i A');

        $response = [
          'success'     => true,
          'converted'   => number_format($conv,2,'.',''),
          'error'       => null,
          'from'        => $from,
          'to'          => $to,
          'amount'      => number_format($amt,2,'.',''),
          'last_update' => $lastUpd,
        ];
      } else {
        $response['error'] = "No rate for {$to}";
      }
    } else {
      $response['error'] = "API error (HTTP {$code})";
    }
  } else {
    $response['error'] = 'Invalid amount or currency.';
  }
}

echo json_encode($response);
exit;
