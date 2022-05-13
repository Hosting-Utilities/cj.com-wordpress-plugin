<?php

// If we don't get a value back from any of the registered site tag objects, we'll use this
class CJ_Site_Tag_Defaults implements CJTagInterface{
    public function getPageType(): string{
        if ($this->is_subcategory()) return 'subCategory';
        if (is_category()) return 'category';
        if (is_front_page()) return 'homepage';
        if ($this->is_login_page()) return 'accountSignup';
        if (is_search()) return 'searchResults';
        return 'information';
    }
    public function getReferringChannel(): string{
        return 'Display';
    }
    public function getCartSubtotal(){
        return 0;
    }
    public function getOrderSubtotal(){
        return 0;
    }
    public function getItems(){
        return [];
    }

    private function is_login_page(){
        return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
    }

    private function is_subcategory($return_boolean=true) {
        $result = false;
        if (is_category()) {
            $this_category = get_queried_object();
            if (0 != $this_category->parent) // Category has a parent
                $result = $return_boolean ? true : $this_category;
        }
        return $result;
    }

    public function isThankYouPage(): bool{ return false; }
    public function getOrderId(): string{ return ''; }
    public function getCurrency(): string{ return ''; }
    public function getDiscount(){ return ''; }
    public function getCoupon(): string{ return ''; }
}
