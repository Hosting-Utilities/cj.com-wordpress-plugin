<?php
/*
  In the cj-tracking.php file, set CJ_TRACKING_RUN_UNIT_TESTS to true to run these tests.
  The tests will then get run whenever you visite a page on the frontend.
  The tests bypass WooCommerce, so that we do not have to create fake orders on the website, but this makes the tests
  incomplete and not that comprehensive. A new set of tests that use WooCommerce would need to be added to make
  the test suite complete.
  You must have PHP 7 installed to run these tests.
*/

if (version_compare(PHP_VERSION, '7.0.0') === -1){
  exit("You must have PHP 7 or greater to run the unit tests");
}

class TestFail extends Exception { }

class Mock_CJ_Tracking_Order {
  private $_items;
  private $_coupons;

  function __construct($items, $coupons, $id){
    $this->_items = $items;
    $this->_coupons = $coupons;
    $this->_id = $id;
  }

  function get_discount_total() {
    $coupon_total = 0;
    foreach($this->_coupons as $coupon){
      $coupon_total += $coupon->amount();
    }
    return $coupon_total;
  }

  function get_items($val=null){
    if ($val == "coupon"){
      return $this->_coupons;
    }
    return $this->_arr_to_order_items($this->_items);
  }
  function _arr_to_order_items($items){
      $ret = array();
      foreach ($items as $key => $item){
          $ret[$key] = new Mock_CJ_Tracking_Order_Item($item);
      }
      return $ret;
  }

  function get_item_meta($id, $attr, $_){
    return $this->_items[$id][$attr] ;
  }

  function __toString(){
    return (string)$this->_id;
  }

  function add_order_note() { }

  function get_id() { return 1; }

  function get_currency() { return 'USD'; }

}

class Mock_CJ_Tracking_Order_Item {
    function __construct($item) {
      $this->_item = $item;
    }
    function get_product(){
        return new Mock_CJ_Tracking_Order_Product($this->_item['sku'], $this->_item['_line_total']);
    }
    function get_quantity(){
        return $this->_item["_qty"];
    }
}
class Mock_CJ_Tracking_Order_Product {
    function __construct($sku, $line_total) {
      $this->sku = $sku;
      $this->line_total = $line_total;
    }
    function get_sku(){
        return $this->sku;
    }
    function get_name(){
        return 'test_product_'.$this->sku;
    }
    function get_price(){
        return $this->line_total;
    }

    function is_type($type) {
        return $type == 'single';
    }
}

class Mock_CJ_Tracking_Coupon{
  private $_code;
  private $_discount;

  function __construct($code, $discount) {
    $this->_code = $code;
    $this->_discount = $discount;
  }

  function get_code(){
    return $this->_code;
  }

  function amount(){
    return $this->_discount;
  }
}

class Mock_WC_Object{
    public $session;
    function __construct() {
      $this->session = new Mock_WC_Session();
    }
}

class Mock_WC_Session{
    function __construct() {
    }
    function get($attr){
        return "test_$attr";
    }
}

function WC(){
    return new Mock_WC_Object();
}

/*
Use the run_all_tests() function to run all of the tests.
Test methods must start with the word "test" to be recocnized as a test
*/
class CJ_Tracking_Tests{
  private $stop_on_error;

  function __construct($stop_on_errors=true) {
    $this->stop_on_error = $stop_on_errors;
  }

  public static function run_all_tests(){
    assert(CJ_TRACKING_RUN_UNIT_TESTS == true);
    $tests_obj = new CJ_Tracking_Tests($stop_on_errors=false);
    $funcs = get_class_methods("CJ_Tracking_Tests");
    sort($funcs);
    $res = array();
    foreach($funcs as $func){
      if (strpos($func, 'test') === 0 && $func !== "testcase") {
        $res[] = call_user_func(array($tests_obj, $func));
      }
    }
    echo "<br/>";
    echo count(array_filter($res, function($x){return ($x === true);})) . " / " . count($res) . " tests passed <br/>";
    echo count(array_filter($res, function($x){return ($x === false);})) . " / " . count($res) . " tests failed <br/>";
    exit("<br />Finished all of the CJ Tracking tests<br />");
  }

  public static function run_test($test_num){
    $tests_obj = new CJ_Tracking_Tests($stop_on_errors=false);
    $funcs = get_class_methods("CJ_Tracking_Tests");
    sort($funcs);
    $tests = array();
    foreach($funcs as $func){
      if (strpos($func, 'test') === 0 && $func !== "testcase") {
        $tests[] = $func;
      }
    }
    $res = call_user_func(array($tests_obj, $tests[$test_num-1]));
    exit($res ? "test passed :)" : "");
  }

  /*
  $items must be an array of sku, quantity, and price
  $coupons must be an array of code and discount
  $expected url is the url expected to be generated in the tracking code
  $test_name is a unique identifier for each test
  */
  private function testcase($items, $coupons, $order_id, $expected_url, $test_name){
    try{

      $mock_coupons = [];
      foreach ($coupons as $coupon){
        $mock_coupons[] = new Mock_CJ_Tracking_Coupon($coupon["code"], $coupon["discount"]);
      }

      $res = $this->tracking_code($items, $mock_coupons, $order_id);
      $this->check_url($res, $expected_url);

      return true;

    } catch (Exception $e) {
        if (! $this->stop_on_error){
        echo "<br/>Test Failed: " . $test_name . " ";
        echo $e->getMessage() . "\n";
        echo "<pre style='color: #cc0000;'>";
        print_r($e->getTraceAsString());
        echo "</pre>";
        return false;
      } else {
        echo "<br/><br/><b>Test failed: " . $test_name . " </b><br/>";
        throw $e;
      }
    }
  }

  private function tracking_code($items, $coupons, $order_id){
    ob_start();
    $order = new Mock_CJ_Tracking_Order($items, $coupons, $order_id);
    $ret = cj_tracking($order);
    // throw away the ifram echoed by cj_tracking, instead, we will go off of the url returned by cj_tracking
    ob_end_clean();
    return $ret;
  }

  private function check_url($url, $expected){
    if ($url !== $expected){
      throw new TestFail("<br/>Received the URL {$url} <br/>But expected&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; {$expected}");
    }
  }

  public function test_01__simple_test__bypassing_woocommerce(){
    return $this->testcase($items=array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
      ),
      $coupons=array(
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD",
      $test=__FUNCTION__
    );
  }

  public function test_02__coupon_test__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
      ),
      $coupons=array(
        array("code"=>"couponA", "discount"=>10.00),
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD&COUPON=couponA&DISCOUNT=10",
      $test=__FUNCTION__
    );
  }

  public function test_03__multiple_products__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
        array("sku"=>"b", "_qty"=>1, "_line_total"=>10.00),
        array("sku"=>"c", "_qty"=>1, "_line_total"=>10.00),
      ),
      $coupons=array(
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&ITEM2=b&AMT2=10&QTY2=1&ITEM3=c&AMT3=10&QTY3=1&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD",
      $test=__FUNCTION__
    );
  }

  public function test_04__quantities__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
        array("sku"=>"b", "_qty"=>2, "_line_total"=>10.00),
        array("sku"=>"c", "_qty"=>5, "_line_total"=>10.00),
      ),
      $coupons=array(
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&ITEM2=b&AMT2=10&QTY2=2&ITEM3=c&AMT3=10&QTY3=5&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD",
      $test=__FUNCTION__
    );
  }

  public function test_05__line_totals__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
        array("sku"=>"b", "_qty"=>2, "_line_total"=>13.33),
        array("sku"=>"c", "_qty"=>5, "_line_total"=>17.70),
      ),
      $coupons=array(
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&ITEM2=b&AMT2=13.33&QTY2=2&ITEM3=c&AMT3=17.7&QTY3=5&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD",
      $test=__FUNCTION__
    );
  }

  public function test_06__multiple_coupons__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
      ),
      $coupons=array(
        array("code"=>"couponA", "discount"=>1.00),
        array("code"=>"couponB", "discount"=>3.00),
        array("code"=>"couponC", "discount"=>4.00),
      ),
      $order_id=1,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD&COUPON=couponA,couponB,couponC&DISCOUNT=8",
      $test=__FUNCTION__
    );
  }

  public function test_07__everything__bypassing_woocommerce(){
    return $this->testcase(array(
        array("sku"=>"a", "_qty"=>1, "_line_total"=>10.00),
        array("sku"=>"b", "_qty"=>2, "_line_total"=>13.33),
        array("sku"=>"c", "_qty"=>5, "_line_total"=>17.70),
      ),
      $coupons=array(
        array("code"=>"couponA", "discount"=>1.00),
        array("code"=>"couponB", "discount"=>3.00),
        array("code"=>"couponC", "discount"=>4.00),
      ),
      $order_id=7254,
      $expected_url="https://www.emjcd.com/tags/c?containerTagId=XXXXX&ITEM1=a&AMT1=10&QTY1=1&ITEM2=b&AMT2=13.33&QTY2=2&ITEM3=c&AMT3=17.7&QTY3=5&CID=XXXXXXX&OID=1&TYPE=XXXXXXX&CJEVENT=test_cjevent&CURRENCY=USD&COUPON=couponA,couponB,couponC&DISCOUNT=8",
      $test=__FUNCTION__
    );
  }

}

if ( CJ_TRACKING_RUN_UNIT_TESTS === true){
  CJ_Tracking_Tests::run_all_tests();
  //CJ_Tracking_Tests::run_test(1); //uncomment to run a specific test
}
