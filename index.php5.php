<?php

# Input Constants
@define('AMOUNT', $_GET['amount'] !== null ? floatval($_GET['amount']) : 0);
@define('FIAT', $_GET['fiat'] !== null ? strtoupper($_GET['fiat']) : 'USD');
    
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
            
        } finally {
            if ($fp) {
                @fclose($fp);
            }            
        }
    }
}

echo '<h1>Fiat to NAV Converter</h1>';

echo '<b>Example: http://localhost:8000/embed.php?fiat=gbp&amp;amount=20</b>';

echo '<p></p>';

echo 'Input Amount: ' . currentFiatSymbol() . AMOUNT;

echo '<p></p>';

echo '1 BTC = ' . currentFiatSymbol() . currentPriceBTC();

echo '<p></p>';

printf('1 NAV = %.8f BTC', currentPriceNAV());

echo '<p></p>';

echo currentFiatSymbol() . ' -> BTC: ' . amountToBTC();

echo '<p></p>';

echo currentFiatSymbol() . ' -> NAV: ' . amountToNAV();

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