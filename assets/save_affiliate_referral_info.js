(function () {

    function setCookie(key, val, expdays, path='/') {
        document.cookie = encodeURIComponent(key)
            + '=' + encodeURIComponent(val)
            + (expdays ? '; max-age=' + expdays*60*60*24 : '')
            + '; domain=' + window.location.hostname.replace(/^www./, '')
            + (path ? '; path=' + path : '')
            //+ (location.protocol === 'http:' && window.location.hostname.endsWith('.local') ? "" : "; secure")
            + (location.protocol === 'http:' ? '' :  '; secure')
            //+ '; secure'
    }

    var cjevent = (new URL(window.location.href)).searchParams.get('cjevent')
    if (cjevent) {
        var days_in_month = 31
        setCookie( 'cje', cjevent, typeof cj_tracking_cookie_duration !== 'undefined' ? cj_tracking_cookie_duration : days_in_month*13 )
        console.log('Got cjevent ', cjevent)
    }

})();
