<?php

require __DIR__ . '/vendor/autoload.php';

use \Endroid\QrCode\QrCode;

# Input Constants

# FIAT input
@define('AMOUNT', $_GET['amount'] !== null ? floatval($_GET['amount']) : 0);
# NAV input
@define('CONVERT_AMOUNT', $_GET['convert_amount'] !== null ? floatval($_GET['convert_amount']) : 0);
# FIAT with USD base
@define('FIAT', $_GET['fiat'] !== null ? strtoupper($_GET['fiat']) : 'USD');
@define('ADDRESS', str_replace('"', '', $_GET['address']));
@define('GUI', $_GET['gui'] == 'true' || $_GET['gui'] == 1);

# Convert FIAT or NAV, but not both
if (AMOUNT > 0 && CONVERT_AMOUNT > 0) {
    header('Content-Type: application/json');
        
    die('{"error": "AMOUNT or CONVERT_AMOUNT can be set, but not both"}');
}

# File Constants
define('BTC_FILE', '/tmp/navcalc.pairs.json');
define('NAV_FILE', '/tmp/navcalc.btc_nav.json');
define('FIAT_FILE', '/tmp/navcalc.fiat.json');

# Ticker Constants
define('BTC_TICKER', 'https://blockchain.info/ticker');
define('BTC_TICKER_FAIL', "An Error Occured: Couldn't get or save BTC pairs.");
define('NAV_TICKER', 'https://poloniex.com/public?command=returnTicker');
define('NAV_TICKER_FAIL', "An Error Occured: Couldn't get or save NAV pairs.");
define('FIAT_TICKER', 'https://api.fixer.io/latest?base=USD');
define('FIAT_TICKER_FAIL', "An Error Occured: Couldn't get or save BTC pairs.");

# Caching Related Functions

function isRefreshingBTCCache(): bool {
    return filemtime(BTC_FILE) < strtotime('-5 minutes');
}

function isRefreshingNAVCache(): bool {
    return filemtime(NAV_FILE) < strtotime('-5 minutes');
}

function isRefreshingFIATCache(): bool {
    return filemtime(FIAT_FILE) < strtotime('-5 minutes');
}

function isRefreshingCache(): bool {
    return isRefreshingBTCCache() ||
        isRefreshingNAVCache() ||
        isRefreshingFIATCache();
}

function isFileExists(): bool {
    return file_exists(BTC_FILE) &&
        file_exists(NAV_FILE) &&
        file_exists(FIAT_FILE);
}

function isRefreshingContents(): bool {
    return !isFileExists() || isRefreshingCache();    
}

# Pair Pricing Functions

function currentPriceFIAT() {
    $data = file_get_contents(FIAT_FILE);
    $v = @json_decode($data, true)['rates'][FIAT];
    return $v !== null ? floatval($v) : 1;
}

function currentPriceNAV(): float {
    $data = file_get_contents(NAV_FILE);
    $v = @json_decode($data, true)['BTC_NAV']['last'];

    return $v !== null ? floatval($v) : 0;
}

function currentPriceBTC($k = FIAT): float {
    $data = file_get_contents(BTC_FILE);
    $v = @json_decode($data, true)[$k]['last'];

    return $v !== null ?
               floatval($v)
               : @json_decode($data, true)['USD']['last'];;
}

function currentFiatSymbol(): string {
    $data = file_get_contents(BTC_FILE);
    $v = @json_decode($data, true)[FIAT]['symbol'];

    return $v !== null ? $v : '$';    
}


function amountToBTC(): float {
    return AMOUNT / currentPriceBTC();
}

function amountToNAV(): float {
    return amountToBTC() / currentPriceNAV();
}

function navToBTC(): float {
    return floatval(currentPriceNAV() * CONVERT_AMOUNT);
}

function btcToFIAT(): float {
    return navToBTC() * currentPriceBTC();
}

# Create or Update Data 
if (isRefreshingContents()) {

    $file_info = [
        [BTC_FILE, BTC_TICKER, BTC_TICKER_FAIL],
        [NAV_FILE, NAV_TICKER, NAV_TICKER_FAIL],
        [FIAT_FILE, FIAT_TICKER, FIAT_TICKER_FAIL]
    ];

    foreach ($file_info as $f) {
        $fp = null;

        try {
            $content = file_get_contents($f[1]);

            if ($fp = fopen($f[0], 'w+')) {
                fwrite($fp, $content);
                fclose($fp);
            }            

        } catch (Exception $e) {        
            echo $f[2];
        }

        if ($fp) {
            @fclose($fp);
        }
    }
}

# QR related
function qrAddress(): string {

    # max is used because amount and convert_amount can't be set at the same time
    return 'navcoin:' . ADDRESS . '?amount=' . max(amountToNAV(), CONVERT_AMOUNT);
}

if (!GUI) {

    header('Content-Type: application/json');

    $format = '{"current-fiat-rate": %f, ' .
            '"current-nav-price": "%.8f", ' .
            '"current-btc-price": "%.8f", ' .
            '"btc-amount": "%.8f", ' .
            '"nav-amount": "%.8f", ' .
            '"fiat-amount": "%.8f", ' .
            '"to-fiat-amount": %f, ' .
            '"to-btc-amount": %f, ' .
            '"fiat-symbol": "%s", ' .
            '"address": "%s", ' .
            '"qr": "%s"}';


    $qrCode = new QrCode();

    $qrCode
        ->setText(qrAddress())
        ->setSize(500)
        ->setPadding(8)
        ->setErrorCorrection('high')
        ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
        ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
        ->setLabel('Scan NAV QR')
        ->setLabelFontSize(16)
        ->setImageType(QrCode::IMAGE_TYPE_PNG);

    printf($format,
           currentPriceFIAT(),
           currentPriceNAV(),
           currentPriceBTC(),
           amountToBTC(),
           amountToNAV(),
           AMOUNT,
           # Only returns a value greater than 0 if CONVERT_AMOUNT is set.
           # This is for NAV to FIAT conversion
           btcToFIAT(),
           navToBTC(),
           currentFiatSymbol(),
           ADDRESS,
           $qrCode->getDataUri());

} else {

    ?>

    <html>
	<head>
    <script src="qrcode.min.js"></script>
	</head>

	<body>

	    <?php
	    
	    echo 'Input Amount: ' . currentFiatSymbol() . AMOUNT;

	    echo '<p></p>';

	    echo '1 BTC = ' . currentFiatSymbol() . currentPriceBTC();

	    echo '<p></p>';

	    printf('1 NAV = %.8f BTC', currentPriceNAV());

	    echo '<p></p>';

	    echo currentFiatSymbol() . ' -> BTC: ' . amountToBTC();

	    echo '<p></p>';

	    echo currentFiatSymbol() . ' -> NAV: ' . amountToNAV();

        echo '<h3>Set ?convert_amount to convert NAV to BTC and FIAT</h3>';

        echo 'NAV -> BTC: ' . navToBTC();

        echo '<p></p>';

        echo 'BTC -> ' . currentFiatSymbol() . ': ' . btcToFIAT();

	    echo '<h3>Address QR Code</h3>';

	    if (ADDRESS === null) {

		echo 'Please set ?address / &amp;address to see QR Code.';
		
            } else {

            ?>

		<div id="qrcode"></div>
		<script type="text/javascript">
		 new QRCode(document.getElementById("qrcode"), "<?=qrAddress();?>");
		</script>

	    <?php

	    }

	    ?>
	    
	    <h3> Supported Pairs </h3>
	    USD<br />
            ISK<br />
            HKD<br />
            TWD<br />
            CHF<br />
            EUR<br />
            DKK<br />
            CLP<br />
            CAD<br />
            CNY<br />
            THB<br />
            AUD<br />
            SGD<br />
            KRW<br />
            JPY<br />
            PLN<br />
            GBP<br />
            SEK<br />
            NZD<br />
            BRL<br />
            RUB<br />
	</body>
    </html>

<?php

}
