<?php

require __DIR__ . '/vendor/autoload.php';

use \Endroid\QrCode\QrCode;

# Input Constants
@define('AMOUNT', $_GET['amount'] !== null ? floatval($_GET['amount']) : 0);
@define('FIAT', $_GET['fiat'] !== null ? strtoupper($_GET['fiat']) : 'USD');
@define('ADDRESS', $_GET['address']);
@define('GUI', $_GET['gui'] == 'true' || $_GET['gui'] == 1);

# File Constants
define('BTC_FILE', '/tmp/navcalc.pairs.json');
define('NAV_FILE', '/tmp/navcalc.btc_nav.json');

# Ticker Constants
define('BTC_TICKER', 'https://blockchain.info/ticker');
define('BTC_TICKER_FAIL', "An Error Occured: Couldn't get or save BTC pairs.");
define('NAV_TICKER', 'https://poloniex.com/public?command=returnTicker');
define('NAV_TICKER_FAIL', "An Error Occured: Couldn't get or save NAV pairs.");

# Caching Related Functions

function isRefreshingBTCCache() {
    return filemtime(BTC_FILE) < strtotime('-5 minutes');
}

function isRefreshingNAVCache() {
    return filemtime(NAV_FILE) < strtotime('-5 minutes');
}

function isRefreshingCache() {
    return isRefreshingBTCCache() || isRefreshingNAVCache();
}

function isFileExists() {
    return file_exists(BTC_FILE) && file_exists(NAV_FILE);
}

function isRefreshingContents() {
    return !isFileExists() || isRefreshingCache();    
}

# Pair Pricing Functions

function currentPriceNAV() {
    $data = file_get_contents(NAV_FILE);
    $v = @json_decode($data, true)['BTC_NAV']['last'];

    return $v !== null ? floatval($v) : 0;
}

function currentPriceBTC() {
    $data = file_get_contents(BTC_FILE);
    $v = @json_decode($data, true)[FIAT]['last'];

    return $v !== null ?
               floatval($v)
               : @json_decode($data, true)['USD']['last'];;
}

function currentFiatSymbol() {
    $data = file_get_contents(BTC_FILE);
    $v = @json_decode($data, true)[FIAT]['symbol'];

    return $v !== null ? $v : '$';    
}

function amountToBTC() {
    return AMOUNT / currentPriceBTC();
}

function amountToNAV() {
    return amountToBTC() / currentPriceNAV();
}

# Create or Update Data 
if (isRefreshingContents()) {

    $file_info = array(
        array(BTC_FILE, BTC_TICKER, BTC_TICKER_FAIL),
        array(NAV_FILE, NAV_TICKER, NAV_TICKER_FAIL)
    );
    
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
function qrAddress() {

    return 'navcoin:' . ADDRESS . '?amount=' . amountToNAV();
}

if (!GUI) {

    header('Content-Type: application/json');

    $format = '{"current-nav-price": "%.8f", ' .
            '"current-btc-price": "%.8f", ' .
            '"btc-amount": "%.8f", ' .
            '"nav-amount": "%.8f", ' .
            '"fiat-amount": %d, ' .
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
           currentPriceNAV(),
           currentPriceBTC(),
           amountToBTC(),
           amountToNAV(),
           AMOUNT,
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
