<?php
/** Combined courier tracking updater. Keep db.php in the same directory. */
date_default_timezone_set('Asia/Karachi');
set_time_limit(0); ignore_user_abort(true);
error_reporting(E_ALL); ini_set('display_errors','1'); ini_set('output_buffering','0');
// Load the DB connection before sending any output; db.php may call session_start().
include_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header('Content-Type: text/html; charset=UTF-8'); header('Cache-Control: no-cache, no-store, must-revalidate'); header('X-Accel-Buffering: no');
function allcron_flush(): void { if (function_exists('ob_flush')) @ob_flush(); @flush(); }
function allcron_section(string $name): void {
    $cardId = 'starting-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    echo '<section class="courier-card" id="' . htmlspecialchars($cardId, ENT_QUOTES, 'UTF-8') . '"><div class="courier-head"><div class="courier-icon">↻</div><div><h2>'
       . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
       . '</h2><p>Tracking sync started</p></div><span class="status-pill running">Running</span></div><div class="courier-progress"><span></span></div></section>';
    allcron_flush();
}
function allcron_done(string $name, float $started): void {
    $cardId = 'starting-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    echo '<script>(function(){var el=document.getElementById(' . json_encode($cardId) . ');if(el)el.remove();})();</script>';
    $spent = max(0, microtime(true) - $started);
    $spentText = $spent >= 60
        ? floor($spent / 60) . "m " . round(fmod($spent, 60)) . "s"
        : number_format($spent, 2) . "s";
    echo '<section class="courier-card done-card"><div class="courier-head"><div class="courier-icon">✓</div><div><h2>'
       . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
       . ' — Finished</h2><p>Finished at ' . date('Y-m-d H:i:s') . ' (Pakistan time) · Time spent: '
       . htmlspecialchars($spentText, ENT_QUOTES, 'UTF-8')
       . ' · Time remaining: 0s</p></div><span class="status-pill finished">Finished</span></div></section>';
    allcron_flush();
}
function allcron_error(string $name, Throwable $e): void {
    $cardId = 'starting-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    echo '<script>(function(){var el=document.getElementById(' . json_encode($cardId) . ');if(el)el.remove();})();</script>';
    echo '<section class="courier-card error-card"><div class="courier-head"><div class="courier-icon">!</div><div><h2>'
       . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
       . ' needs attention</h2><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</p></div><span class="status-pill failed">Issue</span></div></section>';
    allcron_flush();
}
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Courier Operations</title>
<style>
:root{--ink:#172033;--muted:#64748b;--line:#e2e8f0;--blue:#2563eb}
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:var(--ink);font:15px/1.55 Inter,Segoe UI,Arial,sans-serif}
.dashboard{max-width:1180px;margin:0 auto;padding:30px 22px 54px}
.hero{background:linear-gradient(120deg,#111c3a,#1d4ed8);color:#fff;border-radius:20px;padding:28px 30px;margin-bottom:20px;box-shadow:0 12px 30px #0f172a18}
.hero h1{margin:0;font-size:clamp(24px,3vw,34px);letter-spacing:-.5px}.hero p{margin:7px 0 0;color:#dbeafe}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0 22px}
.summary{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px}.summary strong{display:block;font-size:20px}.summary span{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.07em}
.courier-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px 22px;margin:14px 0;box-shadow:0 4px 16px #0f172a08}
.courier-head{display:flex;align-items:center;gap:13px}.courier-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#eff6ff;color:#1d4ed8;font-size:24px;font-weight:700;flex-shrink:0}
.courier-head h2{font-size:18px;margin:0}.courier-head p{margin:3px 0 0;color:var(--muted);overflow-wrap:anywhere}
.status-pill{margin-left:auto;border-radius:99px;padding:5px 10px;font-size:12px;font-weight:700;white-space:nowrap}.running{background:#dbeafe;color:#1d4ed8}.failed{background:#fee2e2;color:#b91c1c}.finished{background:#dcfce7;color:#166534}.done-card{border-color:#bbf7d0}
.courier-progress{height:5px;background:#eef2f7;border-radius:99px;margin-top:18px;overflow:hidden}.courier-progress span{display:block;width:45%;height:100%;background:#3b82f6;border-radius:99px;animation:progress 1.4s ease-in-out infinite alternate}
.error-card{border-color:#fecaca;background:#fffafa}.error-card .courier-icon{background:#fee2e2;color:#b91c1c}
table{border-collapse:collapse;max-width:100%}th,td{padding:10px 14px;border:1px solid var(--line)}th{background:#f8fafc}
@keyframes progress{from{transform:translateX(-30%)}to{transform:translateX(140%)}}
@media(max-width:760px){.dashboard{padding:16px 12px 36px}.hero{padding:22px 18px}.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.courier-card{padding:16px}.courier-head{align-items:flex-start}.status-pill{font-size:11px}}
</style></head><body><main class="dashboard"><header class="hero"><h1>Courier Operations</h1><p>Live tracking synchronization across M&amp;P, PostEx, Leopards and Trax.</p></header>
<div class="summary-grid"><div class="summary"><span>Couriers</span><strong>4 services</strong></div><div class="summary"><span>Execution</span><strong>Live run</strong></div><div class="summary"><span>Timezone</span><strong>Pakistan</strong></div><div class="summary"><span>Diagnostics</span><strong>Enabled</strong></div></div>'; allcron_flush();

allcron_section('M&P');
$courierStarted = microtime(true);
try {
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors',1);

include_once __DIR__ . "/db.php";

/*-------------------------------------------------------
| M&P Credentials
-------------------------------------------------------*/

$username  = "WATANIMPORTS_23W47";
$password  = "Watan@47788";
$accountNo = "23W47";

$consignments = [];

$q = $conn->query("
    SELECT DISTINCT tracking_id
    FROM orders
    WHERE tracking_id <> ''
    AND courier='MNP'
    AND status IN ('Shipped','Delivered','Returned')
    ORDER BY id DESC
");

while($r = $q->fetch_assoc())
{
    $consignments[] = trim($r['tracking_id']);
}

if(empty($consignments))
{
    throw new RuntimeException((string)("No shipped orders."));
}

/*-------------------------------------------------------
| Payload
-------------------------------------------------------*/

$payload = [

    "Username"=>$username,
    "Password"=>$password,
    "AccountNo"=>$accountNo,
    "Consignments"=>$consignments

];

/*-------------------------------------------------------
| API
-------------------------------------------------------*/

$url =
"https://mnpcourier.com/mycodapi/api/Tracking/Bulk_Consignment_Tracking_New";

$ch = curl_init();

curl_setopt_array($ch,[

    CURLOPT_URL=>$url,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_TIMEOUT=>60,
    CURLOPT_CONNECTTIMEOUT=>15

]);

$response = curl_exec($ch);

if(curl_errno($ch))
{
    throw new RuntimeException((string)(curl_error($ch)));
}

curl_close($ch);

$json = json_decode($response,true);

if(!$json)
{
    throw new RuntimeException((string)("Invalid JSON"));
}

/*-------------------------------------------------------
| tracking_Details
-------------------------------------------------------*/

if(isset($json[0]['tracking_Details']))
{
    $trackingDetails = $json[0]['tracking_Details'];
}
elseif(isset($json['tracking_Details']))
{
    $trackingDetails = $json['tracking_Details'];
}
else
{
    echo "<pre>";
    print_r($json);
    echo "</pre>";
    throw new RuntimeException("Courier script stopped early.");
}

/*-------------------------------------------------------
| Counters
-------------------------------------------------------*/

$checked = 0;
$delivered = 0;
$returned = 0;
$nochange = 0;

/*----------------------------------------------------
| Amount Invoiced
----------------------------------------------------*/

$amount_invoiced = 0;

if(
    isset($shipment['CNTrackingInvDetail'][0]['AmountInvoiced'])
)
{
    $amount_invoiced =
    (float)$shipment['CNTrackingInvDetail'][0]['AmountInvoiced'];
}

/*-------------------------------------------------------
| Loop
-------------------------------------------------------*/


/*-------------------------------------------------------
| QSR Payment Update
-------------------------------------------------------*/

$qsrUrl = "https://mnpcourier.com/mycodapi/api/Reports/QSR_Report";

$qsrPayload = [
    "UserName"    => $username,
    "Password"    => $password,
    "MonthNumber" => date('n'),
    "Year"        => date('Y'),
    "locationID"  => "82053"
];

$ch = curl_init();

curl_setopt_array($ch,[
    CURLOPT_URL => $qsrUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($qsrPayload)
]);

$qsrResponse = curl_exec($ch);
curl_close($ch);

$qsr = json_decode($qsrResponse,true);

if(isset($qsr[0]['Details']))
{
    foreach($qsr[0]['Details'] as $row)
    {
        $tracking = $conn->real_escape_string(
            trim($row['consignmentNumber'] ?? '')
        );

        $chequeno = trim($row['CHEQUENO'] ?? '');

        if($chequeno != '')
        {
            $chequeno = $conn->real_escape_string($chequeno);

            $conn->query("
            UPDATE orders
SET
    payment_status='Paid',
    chequeno='$chequeno'
WHERE tracking_id='$tracking'
AND courier='MNP'
            ");
        }
    }
}

$tracking = $conn->real_escape_string(
    trim($row['consignmentNumber'] ?? '')
);

$chequeno = trim($row['CHEQUENO'] ?? '');

if($chequeno != '')
{
    $chequeno = $conn->real_escape_string($chequeno);

    $conn->query("
    UPDATE orders
    SET
        payment_status='Paid',
        chequeno='$chequeno'
    WHERE tracking_id='$tracking'
    ");
}




foreach($trackingDetails as $shipment)
{

    $tracking =
    trim($shipment['ConsignmentNumber'] ?? '');
    
    
    /*-----------------------------------------
Only process existing MNP shipment
-----------------------------------------*/

$trackingEsc = $conn->real_escape_string($tracking);

$order = $conn->query("
    SELECT id, status
    FROM orders
    WHERE tracking_id='$trackingEsc'
    AND courier='MNP'
    LIMIT 1
");

if(!$order || !$order->num_rows)
{
    // Shipment does not exist in our database
    continue;
}

$db = $order->fetch_assoc();









    if($tracking=="")
    {
        continue;
    }

    if(empty($shipment['CNTrackingDetail']))
    {
        continue;
    }

    /*
    ----------------------------------------------------
    Find Latest Event by Transaction Time
    ----------------------------------------------------
    */

    $latest = null;
    $latestTime = 0;

    foreach($shipment['CNTrackingDetail'] as $event)
    {

        $time =
        DateTime::createFromFormat(
            'm/d/Y H:i:s',
            trim($event['TransactionTime'])
        );

        if($time)
        {
            $ts = $time->getTimestamp();

            if($ts > $latestTime)
            {
                $latestTime = $ts;
                $latest = $event;
            }
        }

    }

    if(!$latest)
    {
        continue;
    }
    
    

    $status =
    strtolower(trim($latest['TrackingStatus'] ?? ''));

    $narration =
    strtolower(trim($latest['TrackingNarration'] ?? ''));

    $combined =
    $status." ".$narration;

    $checked++;

    $trackingEsc =
    $conn->real_escape_string($tracking);
    
    

/*----------------------------------------------------
| Customer Details
----------------------------------------------------*/

$customer_name = '';

if(!empty($shipment['ConsigneeName']))
    $customer_name = $shipment['ConsigneeName'];
elseif(!empty($shipment['CustomerName']))
    $customer_name = $shipment['CustomerName'];

$customer_phone = trim($shipment['ContactNo'] ?? '');

$city = '';


/*----------------------------------------------------
| COD & Courier Charges
----------------------------------------------------*/

$cod_amount = 0;
$courier_cost = 0;

if(isset($shipment['CODAmount']))
{
    $cod_amount = (float)$shipment['CODAmount'];
}
elseif(isset($shipment['CollectAmount']))
{
    $cod_amount = (float)$shipment['CollectAmount'];
}
elseif(isset($shipment['Amount']))
{
    $cod_amount = (float)$shipment['Amount'];
}

if(isset($shipment['ShipmentCharges']))
{
    $courier_cost = (float)$shipment['ShipmentCharges'];
}
elseif(isset($shipment['DeliveryCharges']))
{
    $courier_cost = (float)$shipment['DeliveryCharges'];
}
elseif(isset($shipment['CourierCharges']))
{
    $courier_cost = (float)$shipment['CourierCharges'];
}
elseif(isset($shipment['TotalAmount']))
{
    $courier_cost = (float)$shipment['TotalAmount'];
}



$city = trim($shipment['DestinationCity'] ?? '');

$customer_name  = ucwords(strtolower(trim($customer_name)));
$city           = ucwords(strtolower(trim($city)));

$customer_name  = $conn->real_escape_string($customer_name);
$customer_phone = $conn->real_escape_string(trim($customer_phone));
$city           = $conn->real_escape_string($city);

/*----------------------------------------------------
| Determine Status
----------------------------------------------------*/

$order_status = "Shipped";

$hasDelivered = false;
$hasReturned  = false;

foreach($shipment['CNTrackingDetail'] as $event)
{
    $trackingStatus = strtolower(trim($event['TrackingStatus'] ?? ''));
    $trackingNarration = strtolower(trim($event['TrackingNarration'] ?? ''));

    // Delivered exists anywhere in tracking history
    if($trackingStatus == 'delivered')
    {
        $hasDelivered = true;
    }

    // Returned exists anywhere in tracking history
    $text = $trackingStatus . ' ' . $trackingNarration;

    if(
        strpos($text, 'returned') !== false ||
        strpos($text, 'returned to shipper') !== false ||
        strpos($text, 'return to shipper') !== false ||
        strpos($text, 'shipment returned') !== false ||
        strpos($text, 'rts') !== false
    )
    {
        $hasReturned = true;
    }
}

if($hasDelivered && strtolower($db['status']) !== 'delivered')
{
    $result = $conn->query("
        UPDATE orders
        SET
            status = 'Delivered',
            delivered_at = NOW()
        WHERE tracking_id='$trackingEsc'
        AND courier='MNP'
        AND status <> 'Delivered'
        AND delivered_at IS NULL
        LIMIT 1
    ");

    if(!$result)
    {
        throw new RuntimeException((string)("Delivered update error: " . $conn->error));
    }

    if($conn->affected_rows > 0)
    {
        $delivered++;
    }
}

if($hasReturned && strtolower($db['status']) !== 'returned')
{
    $conn->query("
        UPDATE orders
        SET status='Returned'
        WHERE tracking_id='$trackingEsc'
        AND courier='MNP'
        LIMIT 1
    ");

    $returned++;
}

/*----------------------------------------------------
| Weight
----------------------------------------------------*/

$weight = (float)($shipment['Weight'] ?? 0);


/*----------------------------------------------------
| Weight
----------------------------------------------------*/

$weight = 0;

if(isset($shipment['Weight']))
{
    $weight = (float)$shipment['Weight'];
}


$arrived_ops_date = '';

foreach($shipment['CNTrackingDetail'] as $event)
{
    $statusText = strtolower(
        trim(
            ($event['TrackingStatus'] ?? '') . ' ' .
            ($event['TrackingNarration'] ?? '')
        )
    );

    if(strpos($statusText, 'arrived at ops') !== false)
    {
        $dt = DateTime::createFromFormat(
            'm/d/Y H:i:s',
            trim($event['TransactionTime'])
        );

        if($dt)
        {
            $arrived_ops_date = $dt->format('Y-m-d H:i:s');
        }

        break;
    }
}







/*----------------------------------------------------
| Full Tracking History JSON
----------------------------------------------------*/

$statusHistory = [];

foreach($shipment['CNTrackingDetail'] as $event)
{
    $eventDate = '';

    $dt = DateTime::createFromFormat(
        'm/d/Y H:i:s',
        trim($event['TransactionTime'] ?? '')
    );

    if($dt)
    {
        $eventDate = $dt->format('Y-m-d H:i:s');
    }

    $statusHistory[] = [
        'date' => $eventDate,
        'status' => trim($event['TrackingStatus'] ?? ''),
        'narration' => trim($event['TrackingNarration'] ?? ''),
        'location' => trim($event['Location'] ?? '')
    ];
}

/* Sort by date ascending */

usort($statusHistory, function($a,$b){
    return strtotime($a['date']) <=> strtotime($b['date']);
});

$current_status = $conn->real_escape_string(
    json_encode(
        $statusHistory,
        JSON_UNESCAPED_UNICODE
    )
);




/*----------------------------------------------------
| Update Existing MNP Order Only
----------------------------------------------------*/

$sql = "
    UPDATE orders
    SET
        city='$city',
        weight='$weight',
        current_status='$current_status'
";

if($arrived_ops_date != '')
{
    $sql .= ",
        order_date='$arrived_ops_date'";
}



$sql .= "
    WHERE tracking_id='$trackingEsc'
    AND courier='MNP'
    LIMIT 1
";

if(!$conn->query($sql))
{
    throw new RuntimeException((string)($conn->error));
}

    /*
    ----------------------------------------------------
    Delivered
    ----------------------------------------------------
    */

 

    /*
    ----------------------------------------------------
    Returned
    ----------------------------------------------------
    */

    if(
    strpos($combined,'returned') !== false ||
    strpos($combined,'returned to shipper') !== false ||
    strpos($combined,'return to shipper') !== false ||
    strpos($combined,'shipment returned') !== false ||
    strpos($combined,'rts') !== false
)
{
    // Do not mark again if already Returned
    if(strtolower(trim($db['status'])) === 'returned')
    {
        $nochange++;
        continue;
    }

    $result = $conn->query("
        UPDATE orders
        SET status='Returned'
        WHERE tracking_id='$trackingEsc'
        AND courier='MNP'
        AND status <> 'Returned'
        LIMIT 1
    ");

    if(!$result)
    {
        throw new RuntimeException((string)("Returned update error: " . $conn->error));
    }

    if($conn->affected_rows > 0)
    {
        $returned++;
    }
    else
    {
        $nochange++;
    }

    continue;
}

    $nochange++;

}




/*-------------------------------------------------------
| Output
-------------------------------------------------------*/

echo "
<style>

    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 40px 20px;
        text-align: center;
    }

    .cron-result {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .cron-result h2 {
        margin: 0 0 25px 0;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
    }

    .cron-result table {
        margin: 0 auto;
        border-collapse: collapse;
        text-align: center;
        min-width: 650px;
        background: #fff;
    }

    .cron-result th,
    .cron-result td {
        padding: 12px 20px;
        text-align: center;
    }

    .cron-result th {
        font-weight: 600;
    }

</style>

<div class='cron-result'>

    <h2 style='text-transform: uppercase;'>MNP - Status Update Completed</h2>

    <table border='1' cellpadding='10' cellspacing='0'>

        <tr>
            <th>Checked</th>
            <th>Delivered Updated</th>
            <th>Returned Updated</th>
            <th>No Change</th>
        </tr>

        <tr>
            <td>".$checked."</td>
            <td>".$delivered."</td>
            <td>".$returned."</td>
            <td>".$nochange."</td>
        </tr>

    </table>

</div>
";



echo "
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link href='https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap' rel='stylesheet'>

<style>
  body {
    font-family: 'Poppins', sans-serif;
  }
</style>
";
} catch (Throwable $e) { allcron_error('M&P', $e); }
allcron_done('M&P', $courierStarted);

allcron_section('PostEx');
$courierStarted = microtime(true);
try {
date_default_timezone_set("Asia/Karachi");

set_time_limit(0);

error_reporting(E_ALL);
ini_set('display_errors',1);

include_once __DIR__ . "/db.php";

/*-------------------------------------------------------
| PostEx Bulk Status Updater
|
| Updates only:
| courier = Postex
|
| Statuses:
| Shipped
| Delivered
| Returned
|
-------------------------------------------------------*/


/*-------------------------------------------------------
| PostEx API Credentials
-------------------------------------------------------*/

$token =
"ZmY5MmY5NWM2MDI1NGYwN2E2ZDBkYjM1ODdlOGI5MTU6MDc1OTJjMWE0ZGU4NDU4MWJhY2JmZTcyYjNmYjcxNmY=";


/*-------------------------------------------------------
| API URL
-------------------------------------------------------*/

$apiUrl =
"https://api.postex.pk/services/integration/api/order/v1/track-bulk-order";


/*-------------------------------------------------------
| Counters
-------------------------------------------------------*/

$totalOrders     = 0;
$totalChecked    = 0;
$totalDelivered  = 0;
$totalReturned   = 0;
$totalNoChange   = 0;
$totalErrors     = 0;


/*-------------------------------------------------------
| Load PostEx Orders
-------------------------------------------------------*/

$sql = "

SELECT
    id,
    tracking_id,
    status

FROM orders

WHERE

    courier='Postex'

    AND tracking_id<>''

    AND status IN ('Shipped','Delivered','Returned')

ORDER BY id ASC

";

$result = $conn->query($sql);

if(!$result)
{
    throw new RuntimeException((string)($conn->error));
}


/*-------------------------------------------------------
| Collect Tracking Numbers
-------------------------------------------------------*/

$trackingNumbers = [];

while($row = $result->fetch_assoc())
{

    $tracking =
    trim($row['tracking_id']);

    if($tracking=='')
    {
        continue;
    }

    $trackingNumbers[] = $tracking;

}

$trackingNumbers =
array_values(
array_unique($trackingNumbers)
);

$totalOrders =
count($trackingNumbers);

if($totalOrders==0)
{
    throw new RuntimeException((string)("No PostEx tracking numbers found."));
}


/*-------------------------------------------------------
| Split Into Batches
|
| PostEx works better with smaller requests.
-------------------------------------------------------*/

$batches =
array_chunk(
$trackingNumbers,
100
);

/*-------------------------------------------------------
| Process Each Batch
-------------------------------------------------------*/

foreach($batches as $batch)
{

    

    /*-------------------------------------------------------
    | Build GET URL
    |
    | Format Required By PostEx:
    | TrackingNumbers=123
    | &TrackingNumbers=456
    -------------------------------------------------------*/

    $query = [];

    foreach($batch as $tracking)
    {
        $query[] =
        "TrackingNumbers=" .
        urlencode($tracking);
    }

    $url =
    $apiUrl .
    "?" .
    implode("&",$query);

    /*-------------------------------------------------------
    | cURL Request
    -------------------------------------------------------*/

    $ch = curl_init();

    curl_setopt_array($ch,[

        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPGET => true,

        CURLOPT_HTTPHEADER => [

            "token: ".$token,
            "Accept: application/json"

        ],

        CURLOPT_TIMEOUT => 60,

        CURLOPT_CONNECTTIMEOUT => 20,

        CURLOPT_SSL_VERIFYPEER => false,

        CURLOPT_SSL_VERIFYHOST => false,

        CURLOPT_HEADER => true

    ]);

    $response = curl_exec($ch);

    $curlError = curl_error($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $headerSize = curl_getinfo(
        $ch,
        CURLINFO_HEADER_SIZE
    );

    curl_close($ch);

    $headers =
    substr(
        $response,
        0,
        $headerSize
    );

    $body =
    substr(
        $response,
        $headerSize
    );

    /*-------------------------------------------------------
    | cURL Error
    -------------------------------------------------------*/

    if($curlError)
    {

        echo "<div style='color:red'>";

        echo "<b>cURL Error:</b> ";

        echo htmlspecialchars($curlError);

        echo "</div>";

        $totalErrors++;

        continue;

    }

    /*-------------------------------------------------------
    | HTTP Error
    -------------------------------------------------------*/

    if($httpCode != 200)
    {

        echo "<div style='color:red'>";

        echo "<b>HTTP ".$httpCode."</b>";

        echo "</div>";

        echo "<pre>";

        echo htmlspecialchars($body);

        echo "</pre>";

        $totalErrors++;

        continue;

    }

    /*-------------------------------------------------------
    | Decode JSON
    -------------------------------------------------------*/

    $json =
    json_decode(
        $body,
        true
    );

    if(!$json)
    {

        echo "<b>Invalid JSON Returned</b>";

        echo "<pre>";

        echo htmlspecialchars($body);

        echo "</pre>";

        $totalErrors++;

        continue;

    }

    /*-------------------------------------------------------
    | Validate Response
    -------------------------------------------------------*/

    if(
        empty($json['dist']) ||
        !is_array($json['dist'])
    )
    {

        echo "<b>Unexpected Response</b>";

        echo "<pre>";

        print_r($json);

        echo "</pre>";

        $totalErrors++;

        continue;

    }

    /*-------------------------------------------------------
    | Start Reading Shipments
    -------------------------------------------------------*/

    foreach($json['dist'] as $shipment)
    {

        if(
            empty(
                $shipment['trackingResponse']
            )
        )
        {
            continue;
        }

        $trackingData =
        $shipment['trackingResponse'];

        $totalChecked++;

        $tracking =
        trim(
            $trackingData['trackingNumber'] ?? ''
        );

        if($tracking=='')
        {
            continue;
        }
        
        
        


        $trackingEsc =
        $conn->real_escape_string(
            $tracking
        );
        
                /*-------------------------------------------------------
        | Detect Final Status
        -------------------------------------------------------*/

        $orderStatus = "Shipped";

        $history =
        $trackingData['transactionStatusHistory'] ?? [];

        /*
         * IMPORTANT:
         * Read the current database status before applying the
         * status detected from PostEx.
         *
         * Once an order is Returned, this cron must NEVER
         * change it back to Delivered.
         */
        $currentDbStatus = "";

        $statusQuery = $conn->query("
            SELECT status
            FROM orders
            WHERE tracking_id='$trackingEsc'
              AND courier='Postex'
            LIMIT 1
        ");

        if($statusQuery && $statusRow = $statusQuery->fetch_assoc())
        {
            $currentDbStatus = trim($statusRow['status']);
        }
        
        $lastStatus = "";
        $lastCode   = "";

        /*
        ------------------------------------------
        Check History First (Most Accurate)
        ------------------------------------------
        */

        if(is_array($history))
{
    foreach($history as $event)
    {
        $lastStatus = trim(
            $event['transactionStatusMessage'] ?? ''
        );

        $lastCode = trim(
            $event['transactionStatusMessageCode'] ?? ''
        );
    }

    /*
    ---------------------------------------
    Check ONLY the LAST status
    ---------------------------------------
    */

    if(
        strcasecmp(
            $lastStatus,
            "Delivered to Customer"
        ) === 0
        ||
        $lastCode === "0005"
    )
    {
        $orderStatus = "Delivered";
    }
    elseif(
        strcasecmp(
            $lastStatus,
            "Returned at Merchant Warehouse"
        ) === 0
        ||
        $lastCode === "0006"
    )
    {
        $orderStatus = "Returned";
    }
}

        /*
        ------------------------------------------
        Fallback
        ------------------------------------------
        */

        /*
         * IMPORTANT:
         * Once an order is already Returned in the database,
         * never allow API data to change it to Delivered.
         */
        if($currentDbStatus === "Returned")
        {
            $orderStatus = "Returned";
        }

        if($orderStatus=="Shipped")
        {

            $currentStatus =
            strtolower(
                trim(
                    $trackingData['transactionStatus']
                    ??
                    ''
                )
            );

            if(
    stripos(
        $currentStatus,
        "delivered to customer"
    ) !== false
)
{
    $orderStatus = "Delivered";
}

            

        }

        /*-------------------------------------------------------
        | Build Status History JSON
        -------------------------------------------------------*/

        /*-------------------------------------------------------
| Convert PostEx History
-------------------------------------------------------*/

$timeline = [];

if(is_array($history))
{
    foreach($history as $event)
    {

        $date = "";

        if(!empty($event['updatedAt']))
        {
            $date = date(
                "Y-m-d H:i:s",
                strtotime($event['updatedAt'])
            );
        }

        $timeline[] = [

            "date"      => $date,

            "status"    =>
                trim(
                    $event['transactionStatusMessage']
                    ?? ''
                ),

            "narration" =>
                trim(
                    $event['transactionStatusMessage']
                    ?? ''
                ),

            "location"  =>
                ""

        ];

    }
}



$currentStatusJson =
$conn->real_escape_string(

    json_encode(
        $timeline,
        JSON_UNESCAPED_UNICODE
    )

);

        /*-------------------------------------------------------
        | Delivery Date
        -------------------------------------------------------*/

        $deliveredAt = "";

        if(
            !empty(
                $trackingData['orderDeliveryDate']
            )
        )
        {

            $time =
            strtotime(
                $trackingData['orderDeliveryDate']
            );

            if($time)
            {
                $deliveredAt =
                date(
                    "Y-m-d H:i:s",
                    $time
                );
            }

        }

        /*-------------------------------------------------------
        | Update History
        -------------------------------------------------------*/

        $sql = "

        UPDATE orders

        SET

        current_status='$currentStatusJson'

        ";

        if($deliveredAt!="")
        {

            $sql .= ",

            delivered_at='$deliveredAt'

            ";

        }

        $sql .= "

        WHERE

        tracking_id='$trackingEsc'

        AND courier='Postex'

        ";

        if(!$conn->query($sql))
        {

            echo "<div style='color:red'>";

            echo $conn->error;

            echo "</div>";

            $totalErrors++;

            continue;

        }
        
        
if(
    $orderStatus != "Delivered" &&
    $orderStatus != "Returned"
)
{
    $orderStatus = "Shipped";
}


        
        
        /*-------------------------------------------------------
        | Update Order Status
        -------------------------------------------------------*/

        /*-------------------------------------------------------
| Update Delivered
-------------------------------------------------------*/

if($orderStatus=="Delivered")
{
    $deliverySqlDate =
        $deliveredAt != ""
        ? "'" . $conn->real_escape_string($deliveredAt) . "'"
        : "IFNULL(delivered_at,NOW())";

    $update = $conn->query("

        UPDATE orders

        SET
            status='Delivered',
            delivered_at=$deliverySqlDate

        WHERE
            tracking_id='$trackingEsc'

        AND courier='Postex'

        AND status NOT IN ('Delivered','Returned')

        LIMIT 1

    ");

    if($update)
    {
        if($conn->affected_rows > 0)
        {
            $totalDelivered++;
        }
    }
    else
    {
        echo "<div style='color:red;'>
            Database Error : ".$conn->error."
        </div>";

        $totalErrors++;
    }

    /*
     * IMPORTANT:
     * current_status has already been updated above.
     * Do not update current_status again.
     */
    continue;
}
        
        
       /*-------------------------------------------------------
| Update Returned
-------------------------------------------------------*/

if($orderStatus=="Returned")
{
    $update = $conn->query("

        UPDATE orders

        SET
            status='Returned'

        WHERE
            tracking_id='$trackingEsc'

        AND courier='Postex'

        AND status <> 'Returned'

        LIMIT 1

    ");

    if($update)
    {
        if($conn->affected_rows > 0)
        {
            $totalReturned++;
        }
    }
    else
    {
        echo "<div style='color:red'>
            ".$conn->error."
        </div>";

        $totalErrors++;
    }

    /*
     * IMPORTANT:
     * current_status has already been updated above.
     * Do not update current_status again.
     */
    continue;
}


        /*-------------------------------------------------------
        | No Status Change
        -------------------------------------------------------*/

        $totalNoChange++;

        echo "

        

        ";

    }

} // End Batch Loop

/*-------------------------------------------------------
| Finish
-------------------------------------------------------*/

$finishTime = microtime(true);

if(!isset($startTime))
{
    $startTime = $_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true);
}

$executionTime =
round(
    $finishTime - $startTime,
    2
);


/*-------------------------------------------------------
| Output - Same Simple UI as MNP Cron
-------------------------------------------------------*/

echo "
<style>
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 40px 20px;
        text-align: center;
    }

    .cron-result {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .cron-result h2 {
        margin: 0 0 25px 0;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
    }

    .cron-result table {
        margin: 0 auto;
        border-collapse: collapse;
        text-align: center;
        min-width: 650px;
    }

    .cron-result th,
    .cron-result td {
        padding: 12px 20px;
        text-align: center;
    }

    .cron-result th {
        font-weight: 600;
    }
</style>

<div class='cron-result'>

    <h2 style='text-transform: uppercase;'>PostEx - Status Update Completed</h2>

    <table border='1' cellpadding='10' cellspacing='0'>

        <tr>
            <th>Checked</th>
            <th>Delivered Updated</th>
            <th>Returned Updated</th>
            <th>No Change</th>
        </tr>

        <tr>
            <td>".$totalChecked."</td>
            <td>".$totalDelivered."</td>
            <td>".$totalReturned."</td>
            <td>".$totalNoChange."</td>
        </tr>

    </table>

</div>
";

echo "
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link href='https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap' rel='stylesheet'>

<style>
  body {
    font-family: 'Poppins', sans-serif;
  }
</style>
";
} catch (Throwable $e) { allcron_error('PostEx', $e); }
allcron_done('PostEx', $courierStarted);

allcron_section('Leopards');
$courierStarted = microtime(true);
try {
date_default_timezone_set("Asia/Karachi");

// Show fatal errors on this debug version instead of a blank HTTP 500 page.
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo "<div style=\"max-width:900px;margin:40px auto;padding:20px;background:#fff3f3;border:1px solid #ffb3b3;border-radius:10px;font-family:Arial;color:#b00020;\">";
        echo "<h2>Leopards Cron Fatal Error</h2>";
        echo "<b>File:</b> " . htmlspecialchars($error["file"]) . "<br>";
        echo "<b>Line:</b> " . (int)$error["line"] . "<br>";
        echo "<b>Error:</b> " . htmlspecialchars($error["message"]);
        echo "</div>";
    }
});

/*=========================================================
| LEOPARDS STATUS CRONJOB - DEBUG VERSION
| Updates STATUS ONLY
=========================================================*/

/*---------------------------------------------------------
| SHOW ALL PHP ERRORS
---------------------------------------------------------*/

error_reporting(E_ALL);

/*
| Do not show PHP warnings/notices on frontend.
| Still save them in PHP error log.
*/
ini_set("display_errors", "0");
ini_set("display_startup_errors", "0");
ini_set("log_errors", "1");

set_time_limit(300);
ignore_user_abort(true);

/*---------------------------------------------------------
| FORCE LIVE OUTPUT
---------------------------------------------------------*/


/*---------------------------------------------------------
| SIMPLE OUTPUT FUNCTION
---------------------------------------------------------*/

function debugLine($message, $type = "info")
{
    // Detailed Leopards diagnostics stay out of the dashboard; only the final error is shown.
    $GLOBALS['leopardsLastDebugMessage'] = (string)$message;
}


/*=========================================================
| FETCH LEOPARDS TRACKING DETAIL
=========================================================*/

function getLeopardsTrackingDetail(
    $apiKey,
    $apiPassword,
    $tracking
) {
    $url =
        "https://merchantapi.leopardscourier.com/api/" .
        "trackBookedPacket/format/json/";

    $payload = [
        "api_key"      => $apiKey,
        "api_password" => $apiPassword,
        "track_numbers" => $tracking
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            "Accept: application/json",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    $error = curl_error($ch);

    $httpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($response === false) {
        return [
            "success" => false,
            "error" => $error ?: "Unknown cURL error",
            "http_code" => $httpCode,
            "data" => []
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            "success" => false,
            "error" => "Invalid JSON response",
            "http_code" => $httpCode,
            "data" => []
        ];
    }

    return [
        "success" => true,
        "error" => "",
        "http_code" => $httpCode,
        "data" => $json
    ];
}
/*=========================================================
| FETCH MULTIPLE LEOPARDS TRACKING DETAILS IN PARALLEL
| This reduces cron runtime versus one-by-one API calls.
=========================================================*/
function getLeopardsTrackingDetailsBatch(
    $apiKey,
    $apiPassword,
    array $trackings,
    $batchSize = 40
) {
    /*
     * Leopards supports multiple tracking numbers in ONE request,
     * comma-separated. This is much faster than one request per shipment.
     */
    $results = [];

    if (empty($trackings)) {
        return $results;
    }

    $url = "https://merchantapi.leopardscourier.com/api/trackBookedPacket/format/json/";

    $trackings = array_values(array_unique(array_filter(array_map(
        static function ($v) {
            return trim((string)$v);
        },
        $trackings
    ))));

    foreach (array_chunk($trackings, max(1, (int)$batchSize)) as $batch) {

        $payload = [
            "api_key"       => $apiKey,
            "api_password"  => $apiPassword,
            "track_numbers" => implode(",", $batch)
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                "Accept: application/json",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false || $error !== "") {
            foreach ($batch as $tracking) {
                $results[$tracking] = [
                    "success"   => false,
                    "error"     => $error ?: "Unknown cURL error",
                    "http_code" => $httpCode,
                    "data"      => []
                ];
            }
            continue;
        }

        $json = json_decode($response, true);

        if (!is_array($json)) {
            foreach ($batch as $tracking) {
                $results[$tracking] = [
                    "success"   => false,
                    "error"     => "Invalid JSON response",
                    "http_code" => $httpCode,
                    "data"      => []
                ];
            }
            continue;
        }

        $apiOk = (
            $httpCode >= 200 &&
            $httpCode < 300 &&
            (string)($json["status"] ?? "0") === "1"
        );

        if (!$apiOk) {
            $apiError = $json["error"] ?? $json["message"] ?? ("HTTP " . $httpCode);

            if (is_array($apiError)) {
                $apiError = json_encode($apiError, JSON_UNESCAPED_UNICODE);
            }

            foreach ($batch as $tracking) {
                $results[$tracking] = [
                    "success"   => false,
                    "error"     => (string)$apiError,
                    "http_code" => $httpCode,
                    "data"      => $json
                ];
            }
            continue;
        }

        $packetList = [];

        if (isset($json["packet_list"]) && is_array($json["packet_list"])) {
            $packetList = $json["packet_list"];
        } elseif (isset($json["data"]) && is_array($json["data"])) {
            $packetList = $json["data"];
        }

        foreach ($packetList as $packet) {
            if (!is_array($packet)) {
                continue;
            }

            $trackNo = trim((string)(
                $packet["track_number"]
                ?? $packet["tracking_number"]
                ?? ""
            ));

            if ($trackNo === "") {
                continue;
            }

            $results[$trackNo] = [
                "success"   => true,
                "error"     => "",
                "http_code" => $httpCode,
                "data"      => [
                    "status"      => 1,
                    "error"       => 0,
                    "packet_list" => [$packet]
                ]
            ];
        }

        foreach ($batch as $tracking) {
            if (!isset($results[$tracking])) {
                $results[$tracking] = [
                    "success"   => false,
                    "error"     => "Tracking number not returned by Leopards API",
                    "http_code" => $httpCode,
                    "data"      => $json
                ];
            }
        }
    }

    return $results;
}

/*=========================================================
| MAP LEOPARDS HISTORY TO current_status JSON
=========================================================*/

function mapLeopardsTrackingHistory(array $json)
{
    $events = [];

    /*=====================================================
    | EXACT LEOPARDS RESPONSE STRUCTURE
    |
    | packet_list
    |   -> [0]
    |      -> Tracking Detail
    =====================================================*/

    if (
        isset($json["packet_list"]) &&
        is_array($json["packet_list"])
    ) {

        foreach ($json["packet_list"] as $packet) {

            if (!is_array($packet)) {
                continue;
            }

            if (
                isset($packet["Tracking Detail"]) &&
                is_array($packet["Tracking Detail"])
            ) {

                $events =
                    $packet["Tracking Detail"];

                break;
            }
        }
    }


    /*=====================================================
    | MAP EXACT LEOPARDS EVENT KEYS
    =====================================================*/

    $history = [];

    foreach ($events as $event) {

        if (!is_array($event)) {
            continue;
        }


        /*-------------------------------------------------
        | DATE + TIME
        | Exact key: Activity_datetime
        -------------------------------------------------*/

        $date = trim(
            (string)(
                $event["Activity_datetime"] ?? ""
            )
        );


        /*
        | Fallback if Activity_datetime is missing
        */

        if ($date === "") {

            $activityDate = trim(
                (string)(
                    $event["Activity_Date"] ?? ""
                )
            );

            $activityTime = trim(
                (string)(
                    $event["Activity_Time"] ?? ""
                )
            );

            $date = trim(
                $activityDate . " " . $activityTime
            );
        }


        /*-------------------------------------------------
        | STATUS
        | Exact key: Status
        -------------------------------------------------*/

        $status = trim(
            (string)(
                $event["Status"] ?? ""
            )
        );


        /*-------------------------------------------------
        | NARRATION
        |
        | Leopards does not return narration like M&P.
        | Use Status_With_City as descriptive narration.
        -------------------------------------------------*/

        $narration = trim(
            (string)(
                $event["Status_With_City"]
                ?? $event["Status"]
                ?? ""
            )
        );


        /*
        | Add Reason when available
        */

        $reason = trim(
            (string)(
                $event["Reason"] ?? ""
            )
        );

        if ($reason !== "") {

            if ($narration !== "") {
                $narration .= " - " . $reason;
            } else {
                $narration = $reason;
            }
        }


        /*-------------------------------------------------
        | LOCATION
        |
        | Leopards does not provide a separate location key.
        | Extract city from Status_With_City.
        -------------------------------------------------*/

        $location = "";

        $statusWithCity = trim(
            (string)(
                $event["Status_With_City"] ?? ""
            )
        );


        /*
        | Examples:
        |
        | Shipment picked in PESHAWAR
        | Dispatched to GILGIT
        | Arrived at Station in ISLAMABAD
        | Assigned to courier in GILGIT
        | Delivered to GILGIT
        */

        if (
            preg_match(
                '/\b(?:in|to)\s+([A-Za-z][A-Za-z\s\-]+)$/i',
                $statusWithCity,
                $matches
            )
        ) {

            $location = strtoupper(
                trim($matches[1])
            );
        }


        /*-------------------------------------------------
        | SKIP EMPTY EVENT
        -------------------------------------------------*/

        if (
            $date === "" &&
            $status === "" &&
            $narration === "" &&
            $location === ""
        ) {
            continue;
        }


        /*-------------------------------------------------
        | NORMALIZE DATE
        -------------------------------------------------*/

        if ($date !== "") {

            $timestamp = strtotime($date);

            if ($timestamp !== false) {

                $date = date(
                    "Y-m-d H:i:s",
                    $timestamp
                );
            }
        }


        /*-------------------------------------------------
        | EXACT REQUIRED JSON FORMAT
        -------------------------------------------------*/

        $history[] = [

            "date" =>
                $date,

            "status" =>
                $status,

            "narration" =>
                $narration,

            "location" =>
                $location

        ];
    }


    /*=====================================================
    | SORT OLDEST TO NEWEST
    =====================================================*/

    usort(
        $history,
        function ($a, $b) {

            return
                strtotime($a["date"] ?? "") <=>
                strtotime($b["date"] ?? "");
        }
    );


    return $history;
}


/*=========================================================
| EXTRACT ACTUAL LEOPARDS DELIVERY DATE/TIME
=========================================================*/
function getLeopardsDeliveredAt(array $history)
{
    $deliveredAt = '';
    $latestDeliveredTimestamp = 0;

    foreach ($history as $event) {

        $statusText = strtolower(trim(
            (string)($event['status'] ?? '')
        ));

        $narrationText = strtolower(trim(
            (string)($event['narration'] ?? '')
        ));

        $combined = $statusText . ' ' . $narrationText;

        if (strpos($combined, 'delivered') === false) {
            continue;
        }

        $date = trim((string)($event['date'] ?? ''));

        if ($date === '') {
            continue;
        }

        $timestamp = strtotime($date);

        if ($timestamp !== false && $timestamp >= $latestDeliveredTimestamp) {
            $latestDeliveredTimestamp = $timestamp;
            $deliveredAt = date('Y-m-d H:i:s', $timestamp);
        }
    }

    return $deliveredAt;
}




debugLine("STEP 1: PHP script started.", "success");


/*=========================================================
| DATABASE
=========================================================*/

debugLine("STEP 2: Loading db.php...", "info");

$dbFile = __DIR__ . "/db.php";

if (!file_exists($dbFile)) {

    debugLine(
        "ERROR: db.php not found at: " . $dbFile,
        "danger"
    );

    if (stripos((string)$apiError, "no booked packet") !== false || stripos((string)$apiError, "no booked shipment") !== false) {
        throw new RuntimeException("No Booked Shipments Found");
    }
    throw new RuntimeException((string)$apiError);
}

include_once __DIR__ . "/db.php";



/*---------------------------------------------------------
| PAGE START
| Must be AFTER db.php because db.php uses session_start()
---------------------------------------------------------*/





debugLine("STEP 3: db.php loaded.", "success");


/*---------------------------------------------------------
| CHECK CONNECTION
---------------------------------------------------------*/

if (!isset($conn)) {

    debugLine(
        "ERROR: \$conn variable does not exist after loading db.php.",
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

if (!($conn instanceof mysqli)) {

    debugLine(
        "ERROR: \$conn is not a mysqli connection.",
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

if ($conn->connect_errno) {

    debugLine(
        "DATABASE CONNECTION ERROR: " .
        $conn->connect_error,
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

debugLine(
    "STEP 4: Database connected successfully.",
    "success"
);


/*=========================================================
| API CREDENTIALS
=========================================================*/

/*
| Put your Leopards credentials here.
| Do not add extra spaces.
*/

$apiKey = "487F7B22F68312D2C1BBC93B1AEA445B1783283555";

$apiPassword = "AAaa11@@";

if (
    $apiKey === "YOUR_LEOPARDS_API_KEY" ||
    $apiPassword === "YOUR_LEOPARDS_API_PASSWORD"
) {

    debugLine(
        "ERROR: Leopards API credentials are still placeholders.",
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

debugLine(
    "STEP 5: API credentials are configured.",
    "success"
);


/*=========================================================
| DATE RANGE
=========================================================*/

$fromDate = date(
    "Y-m-d",
    strtotime("-14 days")
);

$toDate = date("Y-m-d");

debugLine(
    "STEP 6: Date Range = " .
    $fromDate .
    " to " .
    $toDate,
    "info"
);


/*=========================================================
| API URL
=========================================================*/

$apiUrl =
    "https://merchantapi.leopardscourier.com/api/" .
    "getBookedPacketLastStatus/format/json/";

$query = http_build_query([

    "api_key" =>
        $apiKey,

    "api_password" =>
        $apiPassword,

    "from_date" =>
        $fromDate,

    "to_date" =>
        $toDate

]);

$fullUrl =
    $apiUrl .
    "?" .
    $query;

debugLine(
    "STEP 7: API request prepared.",
    "success"
);


/*=========================================================
| CHECK CURL
=========================================================*/

if (!function_exists("curl_init")) {

    debugLine(
        "ERROR: PHP cURL extension is not enabled.",
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

debugLine(
    "STEP 8: cURL extension available.",
    "success"
);


/*=========================================================
| CALL API
=========================================================*/

debugLine(
    "STEP 9: Calling Leopards API now...",
    "warning"
);

$ch = curl_init();

curl_setopt_array($ch, [

    CURLOPT_URL =>
        $fullUrl,

    CURLOPT_RETURNTRANSFER =>
        true,

    CURLOPT_FOLLOWLOCATION =>
        true,

    CURLOPT_CONNECTTIMEOUT =>
        10,

    CURLOPT_TIMEOUT =>
        45,

    CURLOPT_SSL_VERIFYPEER =>
        true,

    CURLOPT_HTTPHEADER => [
        "Accept: application/json"
    ]

]);

$startTime = microtime(true);

$response = curl_exec($ch);

$elapsed = round(
    microtime(true) - $startTime,
    2
);

$curlError = curl_error($ch);

$curlErrno = curl_errno($ch);

$httpCode = (int) curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$contentType = curl_getinfo(
    $ch,
    CURLINFO_CONTENT_TYPE
);

curl_close($ch);

debugLine(
    "STEP 10: API call finished in " .
    $elapsed .
    " seconds.",
    "success"
);

debugLine(
    "HTTP Code: " . $httpCode,
    $httpCode >= 200 && $httpCode < 300
        ? "success"
        : "danger"
);

debugLine(
    "Content Type: " .
    ($contentType ?: "Not provided"),
    "info"
);


/*=========================================================
| CURL ERROR
=========================================================*/

if ($response === false) {

    debugLine(
        "cURL ERROR #" .
        $curlErrno .
        ": " .
        $curlError,
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}


/*=========================================================
| RESPONSE SIZE
=========================================================*/

debugLine(
    "Response Size: " .
    strlen($response) .
    " bytes",
    "info"
);





/*=========================================================
| HTTP ERROR
=========================================================*/

if ($httpCode < 200 || $httpCode >= 300) {

    debugLine(
        "ERROR: Leopards API returned HTTP " .
        $httpCode,
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}


/*=========================================================
| JSON DECODE
=========================================================*/

debugLine(
    "STEP 11: Decoding JSON...",
    "info"
);

$json = json_decode(
    $response,
    true
);

if (!is_array($json)) {

    debugLine(
        "JSON ERROR: " .
        json_last_error_msg(),
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}

debugLine(
    "STEP 12: JSON decoded successfully.",
    "success"
);


/*=========================================================
| DISPLAY TOP LEVEL KEYS
=========================================================*/

debugLine(
    "Top-level API keys: " .
    implode(
        ", ",
        array_keys($json)
    ),
    "info"
);


/*=========================================================
| CHECK API STATUS
=========================================================*/

$apiStatus = (string)(
    $json["status"] ?? ""
);

debugLine(
    "API status value: " .
    ($apiStatus === "" ? "[blank]" : $apiStatus),
    "info"
);

if ($apiStatus !== "1") {

    $apiError =
        $json["error"]
        ??
        $json["message"]
        ??
        "Unknown API error";

    if (is_array($apiError)) {

        $apiError = json_encode(
            $apiError,
            JSON_UNESCAPED_UNICODE
        );
    }

    debugLine(
        "API ERROR: " .
        (string)$apiError,
        "danger"
    );

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}


/*=========================================================
| DETECT PACKET LIST
|
| Supports different response structures.
=========================================================*/

$packets = [];

$packetSource = "";


/*---------------------------------------------------------
| packet_list
---------------------------------------------------------*/

if (
    isset($json["packet_list"]) &&
    is_array($json["packet_list"])
) {

    $packets =
        $json["packet_list"];

    $packetSource =
        "packet_list";
}


/*---------------------------------------------------------
| data
---------------------------------------------------------*/

elseif (
    isset($json["data"]) &&
    is_array($json["data"])
) {

    $packets =
        $json["data"];

    $packetSource =
        "data";
}


/*---------------------------------------------------------
| response
---------------------------------------------------------*/

elseif (
    isset($json["response"]) &&
    is_array($json["response"])
) {

    $packets =
        $json["response"];

    $packetSource =
        "response";
}


/*---------------------------------------------------------
| Unknown structure
---------------------------------------------------------*/

else {

    debugLine(
        "ERROR: Could not detect shipment list in API response.",
        "danger"
    );

    echo '<pre style="
        background:#111;
        color:#fff;
        padding:15px;
        border-radius:8px;
        overflow:auto;
    ">';

    echo htmlspecialchars(
        print_r($json, true)
    );

    echo '</pre>';

    throw new RuntimeException("Leopards could not continue. Review the API diagnostic immediately above; a no-booked-packets response can mean there were no eligible shipments in the selected date range.");
}


debugLine(
    "STEP 13: Shipment list found in key: " .
    $packetSource,
    "success"
);

debugLine(
    "Total API shipments: " .
    count($packets),
    "success"
);


/*=========================================================
| DISPLAY FIRST PACKET KEYS
=========================================================*/

if (!empty($packets)) {

    $firstPacket = reset($packets);

    if (is_array($firstPacket)) {

        debugLine(
            "First packet keys: " .
            implode(
                ", ",
                array_keys($firstPacket)
            ),
            "info"
        );
    }
}


/*=========================================================
| PREPARE UPDATE
=========================================================*/

$historyStmt = $conn->prepare("

    UPDATE orders

    SET current_status = ?

    WHERE tracking_id = ?

    AND courier = 'Leopards'

    LIMIT 1

");

if (!$historyStmt) {
    throw new RuntimeException((string)("HISTORY PREPARE ERROR: " . $conn->error));
}


/*=========================================================
| UPDATE FINAL STATUS + delivered_at
=========================================================*/

$updateStmt = $conn->prepare("

    UPDATE orders

    SET
        status = ?,
        delivered_at = CASE
            WHEN ? = 'Delivered'
                THEN COALESCE(
                    NULLIF(?, ''),
                    NOW()
                )
            ELSE delivered_at
        END

    WHERE tracking_id = ?

    AND courier = 'Leopards'

    AND LOWER(TRIM(status)) = 'shipped'

    LIMIT 1

");

if (!$updateStmt) {
    throw new RuntimeException((string)("STATUS PREPARE ERROR: " . $conn->error));
}


/*=========================================================
| CHECK IF TRACKING EXISTS IN DATABASE
| Exact tracking lookup keeps this query index-friendly.
=========================================================*/

$checkStmt = $conn->prepare("

    SELECT
        status,
        current_status

    FROM orders

    WHERE tracking_id = ?

    AND courier = 'Leopards'

    LIMIT 1

");

if (!$checkStmt) {
    throw new RuntimeException((string)("CHECK PREPARE ERROR: " . $conn->error));
}


/*=========================================================
| COUNTERS
=========================================================*/

$totalApiRows = 0;

$updatedDelivered = 0;
$updatedReturned = 0;

$cancelledSkipped = 0;
$otherSkipped = 0;
$blankSkipped = 0;
$noDbMatch = 0;
$dbErrors = 0;

$historyUpdated = 0;
$historyEmpty = 0;
$historyFailed = 0;

$notInDatabase = 0;

$apiCallsMade = 0;
$apiCallsSaved = 0;


/*=========================================================
| STEP 1: FIND DATABASE ORDERS
| No tracking API call is made here.
=========================================================*/

$pendingPackets = [];

foreach ($packets as $packet) {

    $totalApiRows++;

    if (!is_array($packet)) {
        $otherSkipped++;
        continue;
    }

    $tracking = trim(
        (string)(
            $packet["track_number"] ?? $packet["tracking_number"] ?? ""
        )
    );

    $apiPacketStatus = trim(
        (string)(
            $packet["booked_packet_status"] ?? ""
        )
    );

    if (
        $tracking === "" ||
        $apiPacketStatus === ""
    ) {
        $blankSkipped++;
        continue;
    }

    $statusLower = strtolower(
        trim($apiPacketStatus)
    );

    if ($statusLower === "cancelled") {
        $cancelledSkipped++;
        $apiCallsSaved++;
        continue;
    }

    $checkStmt->bind_param(
        "s",
        $tracking
    );

    if (!$checkStmt->execute()) {
        $dbErrors++;
        continue;
    }

    $dbOrder = null;

    // get_result() is not available on some shared hosts without mysqlnd.
    if (method_exists($checkStmt, "get_result")) {
        $checkResult = $checkStmt->get_result();
        if ($checkResult) {
            $dbOrder = $checkResult->fetch_assoc();
        }
    } else {
        $checkStmt->store_result();
        $checkStmt->bind_result($dbStatusValue, $dbCurrentStatusValue);
        if ($checkStmt->fetch()) {
            $dbOrder = [
                "status" => $dbStatusValue,
                "current_status" => $dbCurrentStatusValue
            ];
        }
        $checkStmt->free_result();
    }

    if (!$dbOrder) {
        $notInDatabase++;
        $apiCallsSaved++;
        continue;
    }

    $dbStatusLower = strtolower(
        trim(
            (string)(
                $dbOrder["status"] ?? ""
            )
        )
    );

    

    $pendingPackets[$tracking] = [
        "packet"  => $packet,
        "dbOrder" => $dbOrder
    ];
}


/*=========================================================
| STEP 2: FETCH TRACKING DETAILS IN PARALLEL
|
| Previously each tracking request waited for the previous
| request to finish. Now 15 requests run concurrently.
=========================================================*/

$pendingTrackings = array_keys(
    $pendingPackets
);

$trackingResponses =
    getLeopardsTrackingDetailsBatch(
        $apiKey,
        $apiPassword,
        $pendingTrackings,
        40
);

$apiCallsMade = (int)ceil(count($pendingTrackings) / 40);


/*=========================================================
| STEP 3: PROCESS TRACKING HISTORIES
=========================================================*/

foreach ($pendingPackets as $tracking => $pending) {

    $packet = $pending["packet"];
    $dbOrder = $pending["dbOrder"];

    $detailResponse =
        $trackingResponses[$tracking]
        ?? [
            "success" => false,
            "error"   => "No API response",
            "data"    => []
        ];

    if (!$detailResponse["success"]) {
        $historyFailed++;
        continue;
    }

    $history =
        mapLeopardsTrackingHistory(
            $detailResponse["data"]
        );

    $historyCount =
        count($history);

    if ($historyCount <= 0) {
        $historyEmpty++;
        continue;
    }


    /*=====================================================
    | ACTUAL DELIVERY DATE/TIME
    |
    | Uses the Delivered event from Leopards tracking
    | history instead of cron execution time.
    =====================================================*/

    $deliveredAt =
        getLeopardsDeliveredAt(
            $history
        );


    /*=====================================================
    | ENCODE HISTORY
    =====================================================*/

    $currentStatusJson =
        json_encode(
            $history,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($currentStatusJson === false) {
        $historyFailed++;
        continue;
    }


    /*=====================================================
    | UPDATE HISTORY ONLY IF CHANGED
    =====================================================*/

    $oldCurrentStatus =
        (string)(
            $dbOrder["current_status"] ?? ""
        );

    if ($oldCurrentStatus !== $currentStatusJson) {

        $historyStmt->bind_param(
            "ss",
            $currentStatusJson,
            $tracking
        );

        if (!$historyStmt->execute()) {

            $historyFailed++;

        } elseif (
            $historyStmt->affected_rows > 0
        ) {

            $historyUpdated++;
        }
    }


    /*=====================================================
    | DETERMINE FINAL STATUS
    =====================================================*/

    $apiPacketStatus = strtolower(
        trim(
            (string)(
                $packet["booked_packet_status"] ?? ""
            )
        )
    );

    $newStatus = "";

    if ($apiPacketStatus === "delivered") {

        $newStatus = "Delivered";

    } elseif (
        $apiPacketStatus === "returned" ||
        $apiPacketStatus === "return to shipper" ||
        $apiPacketStatus === "returned to shipper"
    ) {

        $newStatus = "Returned";
    }


    /*-----------------------------------------------------
    | Fallback: check latest mapped history
    -----------------------------------------------------*/

    if ($newStatus === "") {

        $latestHistory =
            end($history);

        $latestStatus = strtolower(
            trim(
                (string)(
                    $latestHistory["status"] ?? ""
                )
            )
        );

        $latestNarration = strtolower(
            trim(
                (string)(
                    $latestHistory["narration"] ?? ""
                )
            )
        );

        $latestCombined =
            $latestStatus . " " .
            $latestNarration;

        if (
            strpos(
                $latestCombined,
                "delivered"
            ) !== false
        ) {

            $newStatus = "Delivered";

        } elseif (
            strpos(
                $latestCombined,
                "returned"
            ) !== false ||
            strpos(
                $latestCombined,
                "return to shipper"
            ) !== false
        ) {

            $newStatus = "Returned";
        }
    }


    /*=====================================================
    | NON-FINAL STATUS
    =====================================================*/

    if ($newStatus === "") {

        $otherSkipped++;
        continue;
    }


    /*=========================================================
| PROTECT EXISTING FINAL STATUS
|
| current_status is already updated above.
| Only change status if the existing DB status is Shipped.
| Delivered / Returned remain unchanged.
=========================================================*/

$dbStatus =
    strtolower(
        trim(
            (string)(
                $dbOrder["status"] ?? ""
            )
        )
    );

if ($dbStatus !== "shipped") {

    /*
     * Existing status is already Delivered or Returned.
     * DO NOT change it.
     *
     * current_status has already been updated above.
     */
    $noDbMatch++;
    continue;
}


    /*=====================================================
    | UPDATE STATUS + delivered_at
    =====================================================*/

    $updateStmt->bind_param(
        "ssss",
        $newStatus,
        $newStatus,
        $deliveredAt,
        $tracking
    );

    if (!$updateStmt->execute()) {

        $dbErrors++;
        continue;
    }


    if ($updateStmt->affected_rows > 0) {

        if ($newStatus === "Delivered") {
            $updatedDelivered++;
        }

        if ($newStatus === "Returned") {
            $updatedReturned++;
        }

    } else {

        $noDbMatch++;
    }
}


/*=========================================================
| CLOSE STATEMENTS
=========================================================*/

$updateStmt->close();
$historyStmt->close();
$checkStmt->close();


/*=========================================================
| SIMPLE MNP-STYLE OUTPUT
=========================================================*/

echo "
<style>
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 40px 20px;
        text-align: center;
    }

    .cron-result {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .cron-result h2 {
        margin: 0 0 25px 0;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
    }

    .cron-result table {
        margin: 0 auto;
        border-collapse: collapse;
        text-align: center;
        min-width: 650px;
        background: #fff;
    }

    .cron-result th,
    .cron-result td {
        padding: 12px 20px;
        text-align: center;
    }

    .cron-result th {
        font-weight: 600;
    }
</style>

<div class='cron-result'>

    <h2 style='text-transform: uppercase;'>Leopards - Status Update Completed</h2>

    <table border='1' cellpadding='10' cellspacing='0'>

        <tr>
            <th>Checked</th>
            <th>Delivered Updated</th>
            <th>Returned Updated</th>
            <th>No Change</th>
        </tr>

        <tr>
            <td>" . $totalApiRows . "</td>
            <td>" . $updatedDelivered . "</td>
            <td>" . $updatedReturned . "</td>
            <td>" . $otherSkipped . "</td>
        </tr>

    </table>

</div>
";


echo "
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link href='https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap' rel='stylesheet'>

<style>
  body {
    font-family: 'Poppins', sans-serif;
  }
</style>
";
} catch (Throwable $e) { allcron_error('Leopards', $e); }
allcron_done('Leopards', $courierStarted);

allcron_section('Trax');
$courierStarted = microtime(true);
try {
date_default_timezone_set("Asia/Karachi");
set_time_limit(0);

error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once __DIR__ . "/db.php";

if (!$conn) {
    throw new RuntimeException((string)("Database connection failed."));
}

@$conn->set_charset("utf8mb4");

/*****************************************************************
 * TRAX STATUS UPDATER
 *
 * SHIPPED:
 *   - Check every run.
 *   - Update status when Trax changes it.
 *   - Update current_status every run.
 *   - Save delivered_at when it becomes Delivered.
 *
 * DELIVERED / RETURNED:
 *   - Only check orders booked in the last 30 days (order_date).
 *   - Update current_status only.
 *   - NEVER change the main status.
 *
 * UI:
 *   - No live/row-by-row output.
 *   - UI is rendered only after processing is complete.
 *
 * NOTE:
 *   - Uses parallel cURL when available, with a safe normal-cURL
 *     fallback when cURL multi is disabled by the hosting provider.
 *****************************************************************/

define("TRAX_API_KEY", "S1ZXR2tMTk41bUxjWTNmUEtvSm5oVDVOSE9BcmV2QTFRVWZzbUVSNWp4Q0hENmhFU1ZObFV4S0RnSDAy6a31b257b6e8f");
define("TRAX_API_URL", "https://sonic.pk/api/shipment/track");

/*****************************************************************
 * COUNTERS
 *****************************************************************/

$totalOrders       = 0;
$totalChecked      = 0;
$totalStatusUpdate = 0;
$totalDelivered    = 0;
$totalReturned     = 0;
$totalTimelineOnly = 0;
$totalNoChange     = 0;
$totalErrors       = 0;
$totalShipped      = 0;
$totalCompleted    = 0;
$traxErrorSamples = [];

$startTime = microtime(true);

/*****************************************************************
 * TRAx API
 *****************************************************************/

function traxTrackShipment($trackingNumber)
{
    $url = TRAX_API_URL . "?" . http_build_query([
        "tracking_number" => $trackingNumber,
        "type"            => 1
    ]);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Authorization: " . TRAX_API_KEY,
            "Accept: application/json",
            "Connection: keep-alive"
        ],
        CURLOPT_ENCODING => "",
        CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ["success" => false, "message" => $error];
    if ($httpCode != 200) return ["success" => false, "message" => "HTTP " . $httpCode];

    $json = json_decode($response, true);
    if (!is_array($json)) return ["success" => false, "message" => "Invalid JSON response"];
    if (!isset($json["status"])) return ["success" => false, "message" => "Invalid Trax API response"];
    if ($json["status"] != 0) return ["success" => false, "message" => $json["message"] ?? "Tracking failed"];

    return ["success" => true, "data" => $json["details"] ?? []];
}

/*****************************************************************
 * FAST TRAX API BATCH
 *
 * Only unique tracking IDs from the orders table are requested.
 * Duplicate local orders with the same tracking ID make ONE API call.
 * Up to 80 requests run in parallel when cURL multi is available.
 * Safe normal-cURL fallback is used if the host disables cURL multi.
 *****************************************************************/
function traxTrackShipmentsFast(array $trackingNumbers, $concurrency = 20, $onResult = null)
{
    $results = [];

    $trackingNumbers = array_values(array_unique(array_filter(
        array_map("trim", $trackingNumbers),
        function ($tracking) { return $tracking !== ""; }
    )));

    if (empty($trackingNumbers)) return $results;

    if (
        !function_exists("curl_multi_init") ||
        !function_exists("curl_multi_exec") ||
        !function_exists("curl_multi_select") ||
        !function_exists("curl_multi_info_read")
    ) {
        foreach ($trackingNumbers as $tracking) {
            $results[$tracking] = traxTrackShipment($tracking);
            if (is_callable($onResult)) {
                $onResult($tracking, $results[$tracking]);
            }
        }
        return $results;
    }

    $concurrency = max(1, (int)$concurrency);
    $multiHandle = curl_multi_init();
    $active = [];
    $nextIndex = 0;
    $total = count($trackingNumbers);

    $addHandle = function ($tracking) use ($multiHandle, &$active) {
        $url = TRAX_API_URL . "?" . http_build_query([
            "tracking_number" => $tracking,
            "type" => 1
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: " . TRAX_API_KEY,
                "Accept: application/json",
                "Connection: keep-alive"
            ],
            CURLOPT_ENCODING => "",
            CURLOPT_FOLLOWLOCATION => false
        ]);

        curl_multi_add_handle($multiHandle, $ch);
        $active[(int)$ch] = [
            "handle" => $ch,
            "tracking" => $tracking
        ];
    };

    while ($nextIndex < $total && count($active) < $concurrency) {
        $addHandle($trackingNumbers[$nextIndex++]);
    }

    do {
        do {
            $multiStatus = curl_multi_exec($multiHandle, $running);
        } while ($multiStatus === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($multiHandle)) {
            $ch = $info['handle'];
            $key = (int)$ch;

            if (!isset($active[$key])) {
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
                continue;
            }

            $tracking = $active[$key]['tracking'];
            $response = curl_multi_getcontent($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($error !== "") {
                $api = ["success" => false, "message" => $error];
            } elseif ($httpCode !== 200) {
                $api = ["success" => false, "message" => "HTTP " . $httpCode];
            } else {
                $json = json_decode($response, true);

                if (!is_array($json)) {
                    $api = ["success" => false, "message" => "Invalid JSON response"];
                } elseif (!isset($json["status"])) {
                    $api = ["success" => false, "message" => "Invalid Trax API response"];
                } elseif ((int)$json["status"] !== 0) {
                    $api = ["success" => false, "message" => $json["message"] ?? "Tracking failed"];
                } else {
                    $api = ["success" => true, "data" => $json["details"] ?? []];
                }
            }

            $results[$tracking] = $api;

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
            unset($active[$key]);

            if (is_callable($onResult)) {
                $onResult($tracking, $api);
            }

            /* Immediately replace the finished request. Do not wait for the
             * other requests in this batch to finish. */
            if ($nextIndex < $total) {
                $addHandle($trackingNumbers[$nextIndex++]);
            }
        }

        if ($running && empty($info)) {
            $selectResult = curl_multi_select($multiHandle, 0.2);
            if ($selectResult === -1) {
                usleep(10000);
            }
        }

    } while ($running || !empty($active));

    curl_multi_close($multiHandle);

    return $results;
}

/*****************************************************************
 * LOAD ORDERS
 *
 * Shipped = ALL
 * Delivered/Returned = LAST 30 DAYS BY order_date
 *****************************************************************/

$sql = "
    SELECT
        id,
        tracking_id,
        status,
        order_date
    FROM orders
    WHERE courier = 'Trax'
      AND tracking_id <> ''
      AND (
            status = 'Shipped'
            OR (
                status IN ('Delivered', 'Returned')
                AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
          )
    ORDER BY id ASC
";

$result = $conn->query($sql);

if (!$result) {
    throw new RuntimeException((string)("Database Error: " . $conn->error));
}

/*
 * Group by tracking number so one API response can be applied
 * safely to every matching local order.
 */
$ordersByTracking = [];

while ($row = $result->fetch_assoc()) {

    $tracking = trim($row["tracking_id"]);

    if ($tracking === "") {
        continue;
    }

    if (!isset($ordersByTracking[$tracking])) {
        $ordersByTracking[$tracking] = [];
    }

    $ordersByTracking[$tracking][] = [
        "id"         => (int)$row["id"],
        "tracking"   => $tracking,
        "status"     => trim($row["status"]),
        "order_date" => $row["order_date"]
    ];
}

$trackingNumbers = array_keys($ordersByTracking);
$totalOrders = count($trackingNumbers);

/*****************************************************************
 * PREPARED STATEMENTS
 *****************************************************************/

$timelineStmt = $conn->prepare("
    UPDATE orders
    SET current_status = ?
    WHERE id = ?
");

if (!$timelineStmt) {
    throw new RuntimeException((string)("Prepare Error: " . $conn->error));
}

$shippedStmt = $conn->prepare("
    UPDATE orders
    SET
        status = ?,
        current_status = ?,
        delivered_at = CASE
            WHEN ? = 'Delivered'
                 AND (delivered_at IS NULL OR delivered_at = '0000-00-00 00:00:00')
            THEN ?
            ELSE delivered_at
        END
    WHERE id = ?
      AND status = 'Shipped'
");

if (!$shippedStmt) {
    throw new RuntimeException((string)("Prepare Error: " . $conn->error));
}

$fillDeliveredStmt = $conn->prepare("
    UPDATE orders
    SET
        current_status = ?,
        delivered_at = CASE
            WHEN delivered_at IS NULL
                 OR delivered_at = '0000-00-00 00:00:00'
            THEN ?
            ELSE delivered_at
        END
    WHERE id = ?
      AND status = 'Delivered'
");

/*****************************************************************
 * LIVE PROCESSING FUNCTION
 *
 * Keeps the existing Trax status/update logic in one place while
 * allowing the API batches to report progress immediately.
 *****************************************************************/
function processTraxTrackingResult($tracking, $api)
{
    global $ordersByTracking;
    global $timelineStmt, $shippedStmt, $fillDeliveredStmt;
    global $totalChecked, $totalStatusUpdate, $totalDelivered, $totalReturned;
    global $totalTimelineOnly, $totalNoChange, $totalErrors, $totalShipped, $totalCompleted;


    if (!$api["success"]) {
        $totalErrors++;
        if (count($GLOBALS["traxErrorSamples"]) < 8) {
            $GLOBALS["traxErrorSamples"][] = (string)$tracking . ": " . (string)($api["message"] ?? "Unknown API error");
        }
        return;
    }

    $details = $api["data"];

    if (
        !isset($details["tracking_history"])
        || !is_array($details["tracking_history"])
        || empty($details["tracking_history"])
    ) {
        $totalErrors++;
        if (count($GLOBALS["traxErrorSamples"]) < 8) {
            $GLOBALS["traxErrorSamples"][] = (string)$tracking . ": API returned no tracking_history";
        }
        return;
    }

    $trackingHistory = $details["tracking_history"];
    $totalChecked++;

    /*************************************************************
     * BUILD CURRENT STATUS JSON
     *************************************************************/

    $history = [];

    foreach ($trackingHistory as $event) {

        $eventDate = "";

        if (!empty($event["timestamp"])) {
            $eventDate = date(
                "Y-m-d H:i:s",
                (int)$event["timestamp"]
            );
        }
        elseif (!empty($event["date_time"])) {
            $eventDate = trim($event["date_time"]);
        }

        $history[] = [
            "date" => $eventDate,
            "status" => str_replace(
                "Shipment - ",
                "",
                trim($event["status"] ?? "")
            ),
            "narration" => trim($event["status_reason"] ?? ""),
            "location" => ""
        ];
    }

    $historyJson = json_encode(
        $history,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($historyJson === false) {
        $historyJson = "[]";
    }

    /*************************************************************
     * DETECT STATUS
     *************************************************************/

    $latest = $trackingHistory[0] ?? [];

    $latestStatus = strtolower(
        preg_replace(
            '/\s+/',
            ' ',
            trim($latest["status"] ?? "")
        )
    );

    $newStatus = "Shipped";

    /*************************************************************
     * FIND RETURN ANYWHERE IN COMPLETE HISTORY
     *************************************************************/

    $returnDeliveredToShipper = false;
    $returnStatusDate = "";

    foreach ($trackingHistory as $event) {

        $eventStatus = strtolower(
            preg_replace(
                '/\s+/',
                ' ',
                trim($event["status"] ?? "")
            )
        );

        if (
            strpos(
                $eventStatus,
                "return - delivered to shipper"
            ) !== false
        ) {
            $returnDeliveredToShipper = true;

            $returnStatusDate = !empty($event["timestamp"])
                ? date(
                    "Y-m-d H:i:s",
                    (int)$event["timestamp"]
                )
                : ($event["date_time"] ?? "");

            break;
        }
    }

    if ($returnDeliveredToShipper) {

        $newStatus = "Returned";

    }
    elseif (
        $latestStatus === "shipment - delivered"
        || $latestStatus === "delivered"
    ) {

        $newStatus = "Delivered";

    }
    elseif (
        strpos($latestStatus, "cancel") !== false
    ) {

        /*
         * Keep existing behavior for a Shipped order if Trax
         * explicitly reports cancellation.
         */
        $newStatus = "Cancelled";
    }

    /*************************************************************
     * FIND ACTUAL DELIVERED EVENT TIME
     *************************************************************/

    $deliveredAt = "";

    foreach ($trackingHistory as $event) {

        $eventStatus = strtolower(
            preg_replace(
                '/\s+/',
                ' ',
                trim($event["status"] ?? "")
            )
        );

        if (
            $eventStatus === "shipment - delivered"
            || $eventStatus === "delivered"
        ) {

            if (!empty($event["timestamp"])) {

                $deliveredAt = date(
                    "Y-m-d H:i:s",
                    (int)$event["timestamp"]
                );

            }
            elseif (!empty($event["date_time"])) {

                $deliveryTimestamp =
                    strtotime($event["date_time"]);

                if ($deliveryTimestamp !== false) {

                    $deliveredAt = date(
                        "Y-m-d H:i:s",
                        $deliveryTimestamp
                    );
                }
            }

            if ($deliveredAt !== "") {
                break;
            }
        }
    }

    /*************************************************************
     * APPLY TO LOCAL ORDERS
     *************************************************************/

    foreach ($ordersByTracking[$tracking] as $localOrder) {

        $orderId = (int)$localOrder["id"];
        $oldStatus = $localOrder["status"];

        /*
         * ---------------------------------------------------------
         * SHIPPED
         * ---------------------------------------------------------
         * Every run:
         *   status + current_status are updated.
         *
         * If Delivered:
         *   delivered_at is saved from Trax event time.
         *
         * If Returned:
         *   status becomes Returned.
         */
        if ($oldStatus === "Shipped") {

            $totalShipped++;

            $deliveryDbValue = $deliveredAt !== ""
                ? $deliveredAt
                : date("Y-m-d H:i:s");

            $shippedStmt->bind_param(
                "ssssi",
                $newStatus,
                $historyJson,
                $newStatus,
                $deliveryDbValue,
                $orderId
            );

            if (!$shippedStmt->execute()) {
                $totalErrors++;
                continue;
            }

            if ($newStatus === "Delivered") {

                if ($shippedStmt->affected_rows > 0) {
                    $totalStatusUpdate++;
                    $totalDelivered++;
                }
                else {
                    $totalNoChange++;
                }

            }
            elseif ($newStatus === "Returned") {

                if ($shippedStmt->affected_rows > 0) {
                    $totalStatusUpdate++;
                    $totalReturned++;
                }
                else {
                    $totalNoChange++;
                }

            }
            else {

                if ($shippedStmt->affected_rows > 0) {
                    $totalStatusUpdate++;
                }
                else {
                    $totalNoChange++;
                }
            }

            continue;
        }

        /*
         * ---------------------------------------------------------
         * DELIVERED / RETURNED
         * ---------------------------------------------------------
         * These were selected only if order_date is within
         * the last 30 days.
         *
         * IMPORTANT:
         * Main status is NEVER changed.
         *
         * Only current_status is refreshed.
         */
        if (
            $oldStatus === "Delivered"
            || $oldStatus === "Returned"
        ) {

            $totalCompleted++;

            if (
                $oldStatus === "Delivered"
                && $deliveredAt !== ""
                && $fillDeliveredStmt
            ) {

                $fillDeliveredStmt->bind_param(
                    "ssi",
                    $historyJson,
                    $deliveredAt,
                    $orderId
                );

                if (!$fillDeliveredStmt->execute()) {
                    $totalErrors++;
                }
                elseif ($fillDeliveredStmt->affected_rows > 0) {
                    $totalTimelineOnly++;
                }
                else {
                    $totalNoChange++;
                }

            }
            else {

                $timelineStmt->bind_param(
                    "si",
                    $historyJson,
                    $orderId
                );

                if (!$timelineStmt->execute()) {
                    $totalErrors++;
                }
                elseif ($timelineStmt->affected_rows > 0) {
                    $totalTimelineOnly++;
                }
                else {
                    $totalNoChange++;
                }
            }
        }
    }

}

/*****************************************************************
 * LIVE PROGRESS UI
 *****************************************************************/
$totalProcessed = 0;

echo '<section class="courier-card" id="trax-live-card"><div class="courier-head"><div class="courier-icon">↻</div><div><h2>Trax live progress</h2><p id="trax-stage">Preparing tracking requests…</p></div><span class="status-pill running" id="trax-state">Running</span></div>
<div class="courier-progress" style="height:9px"><span id="trax-bar" style="width:0%;animation:none"></span></div>
<p style="margin:10px 0 0;color:#64748b"><strong id="trax-count">0 / '.(int)$totalOrders.' processed</strong> · <span id="trax-remaining">'.(int)$totalOrders.' remaining</span> · <span id="trax-spent">Time spent: 0s</span> · <span id="trax-eta">Time remaining: calculating…</span></p>
<div class="summary-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
<div class="summary"><span>Updated</span><strong id="trax-updated">0</strong></div>
<div class="summary"><span>Delivered</span><strong id="trax-delivered">0</strong></div>
<div class="summary"><span>Failed</span><strong id="trax-failed">0</strong></div>
<div class="summary"><span>No change</span><strong id="trax-nochange">0</strong></div></div></section>
<script>
window.traxRunStarted = Date.now();
function traxProgress(processed,total,updated,delivered,failed,noChange,remaining,seconds,stage){
 var pct=total?Math.min(100,(processed/total)*100):100;
 var bar=document.getElementById("trax-bar"); if(bar) bar.style.width=pct.toFixed(1)+"%";
 var put=function(id,val){var el=document.getElementById(id);if(el)el.textContent=val;};
 put("trax-stage",stage||"Processing Trax shipments…");
 put("trax-count",processed+" / "+total+" processed"); put("trax-remaining",remaining+" remaining");
 put("trax-updated",updated); put("trax-delivered",delivered); put("trax-failed",failed); put("trax-nochange",noChange);
 var elapsed=Math.floor((Date.now()-window.traxRunStarted)/1000);
 put("trax-spent","Time spent: "+(Math.floor(elapsed/60)?Math.floor(elapsed/60)+"m ":"")+(elapsed%60)+"s");
 if(remaining<=0){put("trax-eta","Time remaining: 0s");put("trax-state","Finished");}
 else if(seconds!==null){put("trax-eta","Time remaining: "+Math.floor(seconds/60)+"m "+(seconds%60)+"s");}
 else{put("trax-eta","Time remaining: calculating…");}
}
</script>';
echo str_repeat(' ', 1024);
allcron_flush();

/*****************************************************************
 * PROCESS ALL TRACKING NUMBERS WITH LIVE UPDATES
 *****************************************************************/
$liveStarted = microtime(true);

/*
 * FAST MODE:
 * - Up to 80 API requests stay active continuously.
 * - When one finishes, the next tracking ID starts immediately.
 * - No MySQL transaction is held open while waiting for Trax API.
 * - Browser progress updates every 5 results to reduce response overhead; every result is still processed.
 */
$progressEvery = 5;

traxTrackShipmentsFast(
    $trackingNumbers,
    80,
    function ($tracking, $api) use (&$totalProcessed, &$liveStarted, $totalOrders, $progressEvery) {

        processTraxTrackingResult($tracking, $api);
        $totalProcessed++;

        if (
            $totalProcessed % $progressEvery !== 0
            && $totalProcessed < $totalOrders
        ) {
            return;
        }

        $elapsed = microtime(true) - $liveStarted;
        $remaining = max(0, $totalOrders - $totalProcessed);
        $estimatedSeconds = $totalProcessed > 0
            ? (int)ceil(($elapsed / $totalProcessed) * $remaining)
            : null;

        $updated = $GLOBALS['totalStatusUpdate'] + $GLOBALS['totalTimelineOnly'];
        $delivered = $GLOBALS['totalDelivered'];
        $failed = $GLOBALS['totalErrors'];
        $noChange = $GLOBALS['totalNoChange'];
        $stage = 'Processing Trax shipment: ' . htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8');

        echo '<script>traxProgress('
            . (int)$totalProcessed . ','
            . (int)$totalOrders . ','
            . (int)$updated . ','
            . (int)$delivered . ','
            . (int)$failed . ','
            . (int)$noChange . ','
            . (int)$remaining . ','
            . ($estimatedSeconds === null ? 'null' : (int)$estimatedSeconds) . ','
            . json_encode($stage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . ');</script>';
        echo str_repeat(' ', 4096);
        flush();
    }
);

$totalUpdated = $totalStatusUpdate + $totalTimelineOnly;
$finishTime = microtime(true);
$executionTime = round($finishTime - $startTime, 2);

echo '<script>traxProgress('
    . (int)$totalProcessed . ','
    . (int)$totalOrders . ','
    . (int)$totalUpdated . ','
    . (int)$totalDelivered . ','
    . (int)$totalErrors . ','
    . (int)$totalNoChange . ',0,0,"Trax finished in '
    . $executionTime . ' seconds");</script>';
flush();

if (!empty($traxErrorSamples)) {
    echo '<div style="max-width:900px;margin:12px auto;padding:14px;border:1px solid #d99;border-radius:10px;font:13px/1.5 Arial,sans-serif">';
    echo '<h3>Trax diagnostic samples (up to 8)</h3><ul>';
    foreach ($traxErrorSamples as $sample) {
        echo '<li>' . htmlspecialchars($sample, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p>These messages help identify whether failures are caused by timeouts, HTTP/API errors, or an unexpected response.</p></div>';
    allcron_flush();
}

/*****************************************************************
 * FINAL LIVE SUMMARY
 *****************************************************************/
$totalUpdated = $totalStatusUpdate + $totalTimelineOnly;

echo '<div style="max-width:900px;margin:0 auto 45px;padding:0 20px;box-sizing:border-box;">
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;text-align:center;">
<strong>TRAX UPDATE COMPLETED</strong><br>
Finished at: '.date('Y-m-d H:i:s').' Pakistan time<br>
Time spent: '.number_format($finishTime - $liveStarted, 2).' seconds &nbsp; | &nbsp; Time remaining: 0 seconds<br>
Processed: '.(int)$totalProcessed.' &nbsp; | &nbsp; Updated: '.(int)$totalUpdated.' &nbsp; | &nbsp; Delivered Updated: '.(int)$totalDelivered.' &nbsp; | &nbsp; Failed: '.(int)$totalErrors.' &nbsp; | &nbsp; Remaining: 0
</div></div>';
} catch (Throwable $e) { allcron_error('Trax', $e); }
allcron_done('Trax', $courierStarted);

echo '<section class="courier-card done-card"><div class="courier-head"><div class="courier-icon">✓</div><div><h2>All courier runs finished</h2><p>Finished at ' . date('Y-m-d H:i:s') . ' Pakistan time. Review each courier summary and any errors above.</p></div><span class="status-pill finished">Finished</span></div></section></main></body></html>';
if (isset($conn) && $conn instanceof mysqli) { try { $conn->close(); } catch (Throwable $ignored) {} }
allcron_flush();
