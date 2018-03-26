<?php
// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init();
    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt(
                $curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type:application/json',
                    'customernumber:<yourCustomerNumber>',
                    'customerlogin:<yourUserName>',
                    'customersignature:<yourSignature>',
                    'interfacepartnernumber:<yourInterfacePartnerNumber',
                    'interfacepartnersignature:<yourInterfacePartnerSignature>',
                    'Accept-Encoding:gzip,deflate,compress'));

            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            }
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data) {
                $url = sprintf("%s?%s", $url, http_build_query($data));
            }

    }

    // Optional Authentication:
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "username:password");

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}

$postData = file_get_contents('php://input');
$d = json_decode($postData);

$service_url = "https://www.dat.de/FinanceLine/rest/Evaluation/getVehicleApproximateValue";
$data = array(
    "request" => array("locale" => array("country" => "DE", "datCountryIndicator" => "DE", "language" => "de"),
        "restriction" => "APPRAISAL",
        "kba" => $d->hsn . "/" . $d->tsn,
        "identificationOrder" => "KBA",
        "approximationSign" => "MINIMUM",
        "registrationDate" => $d->ezl,
        "mileage" => $d->laufleistung,
        "coverage" => "COMPLETE",
        "vatType" => "D"),
);

$response = CallAPI("POST", $service_url, $data);
$valuation = json_decode($response);
$purchasePriceMin = $valuation->{'Dossier'}[0]->{'Valuation'}->{'PurchasePrice'}->{'value'};
$manufacturer = $valuation->{'Dossier'}[0]->{'Vehicle'}->{'ManufacturerName'}->{'value'};
$salesDescription = $manufacturer . ", " . $valuation->{'Dossier'}[0]->{'Vehicle'}->{'SalesDescription'};

$data = array(
    "request" => array("locale" => array("country" => "DE", "datCountryIndicator" => "DE", "language" => "de"),
        "restriction" => "APPRAISAL",
        "kba" => $d->hsn . "/" . $d->tsn,
        "identificationOrder" => "KBA",
        "approximationSign" => "MAXIMUM",
        "registrationDate" => $d->ezl,
        "mileage" => $d->laufleistung,
        "coverage" => "SIMPLE",
        "vatType" => "D"),
);

$response = CallAPI("POST", $service_url, $data);
$valuation = json_decode($response);
$purchasePriceMax = $valuation->{'Dossier'}[0]->{'Valuation'}->{'PurchasePrice'}->{'value'};
$datECode = $valuation->{'Dossier'}[0]->{'Vehicle'}->{'DatECode'};
$container = $valuation->{'Dossier'}[0]->{'Vehicle'}->{'Container'};
$ct = $valuation->{'Dossier'}[0]->{'Vehicle'}->{'ConstructionTime'};

$data = array(
    "request" => array("locale" => array("country" => "DE", "datCountryIndicator" => "DE", "language" => "de"),
        "restriction" => "APPRAISAL",
        "datECode" => $datECode, "container" => $container),
);
$service_url = "https://www.dat.de/FinanceLine/rest/VehicleIdentificationService/getVehicleIdentification";
$response = CallAPI("POST", $service_url, $data);
$ident = json_decode($response);
$otg = $ident->{'Vehicle'}->{'MainTypeGroupName'};

// Get the vehicles image
$fName = "noImage.png";

$data = array("request" => array("datECode" => $datECode, "aspect" => "ANGULARFRONT", "imageType" => "PICTURE"));
$service_url = "https://www.dat.de/FinanceLine/rest/VehicleImagery/getVehicleImagesN";
$response = CallAPI("POST", $service_url, $data);
$pic = json_decode($response);

// If there is a picture write it onto the FS for later referral
if ($pic) {
    $picture = base64_decode($pic->{'images'}[0]->{'imageBase64'}, false);
    $fName = $datECode . ".png";
    $fp = fopen($fName, "wb");
    if ($fp) {
        fwrite($fp, $picture);
        fclose($fp);
    }
}

// Postprocess data
$desc = $manufacturer . " " . $otg;
$descSpeech = str_replace('-', '', $desc);

header('Content-Type: application/json');
$response = array(
    "desc" => $desc,
    "descSpeech" => $descSpeech,
    "priceMin" => round($purchasePriceMin, -2, PHP_ROUND_HALF_UP),
    "priceMax" => round($purchasePriceMax, -2, PHP_ROUND_HALF_UP),
    "fName" => $fName);

echo json_encode($response);
?>