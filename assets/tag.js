(async function(a,b,c,d){
    if (!window.cj) window.cj = {};

    if (! cj_from_php.post_id){
        cj_from_php.post_id = '';
    } else if (isNaN(cj_from_php.post_id) || +cj_from_php.post_id < 1){
        throw Error('Failed to add tracking code due to receiving the invalid post ID: ' + cj_from_php.post_id)
    }

    let use_conversion_tag = cj_from_php.tag_type === 'conversion_tag'
    let action = use_conversion_tag ? 'cj_conversion_tag_data' : 'cj_site_tag_data'

    let url = cj_from_php.ajaxurl + '?action=' + action + '&post_id=' + cj_from_php.post_id
    if (use_conversion_tag){
        //let order_received = (new URL(window.location.href)).searchParams.get("order-received");
        let order_received = cj_from_php.woo_order_id;
        url += '&order-received=' + order_received
    }

    let resp = await fetch(url)
    if ( ! resp.ok)
        throw Error('Error retrieving conversion tag data')
    let data = await resp.json()

    let cj_prop = use_conversion_tag ? 'order' : 'sitePage'
    window.cj[cj_prop] = data

    // // Make sure the service worker's proxy is operational before proceeding any further (remember service worker's are async)
    // // From https://github.com/w3c/ServiceWorker/issues/770
    // // See also https://stackoverflow.com/questions/35704511/serviceworkercontainer-ready-for-a-specific-scripturl/35705984#35705984
    // function registerSW_AndWaitForReady(script, options) {
    //   return navigator.serviceWorker.register(script, options).then(r => {
    //     const incoming = r.installing || r.waiting;
    //     if (r.active && !incoming) {
    //       console.log('cj proxy ready')
    //       return r;
    //     }
    //     return new Promise(resolve => {
    //       const l = e => {
    //         if (e.target.state === 'activated' || e.target.state === 'redundant') {
    //           incoming.removeEventListener('statechange', l);
    //           if (e.target.scriptURL === script) {
    //             console.log('CJ proxy registration')
    //             return resolve(r);
    //           }
    //           throw new Error('Failed to register the CJ service worker')
    //         }
    //       };
    //       incoming.addEventListener('statechange', l);
    //     });
    //   });
    // }
    // let scope = new URL(cj_from_php.proxy_sw_dir).pathname
    // console.log('scope', scope)
    // let temp = await registerSW_AndWaitForReady(cj_from_php.proxy_sw + '?ajaxurl=' + encodeURIComponent(cj_from_php.ajaxurl) + '&ver=0.08', { // TODO use CJ_TRACKING_PLUGIN_VERSION for version number here
    //     scope: scope, // defaults to current directory
    //     //updateViaCache: 'none'
    // });
    // console.log('ready', temp)

    if (cj_from_php.implementation === 'server_side_cookie'){
        url = 'https://www.mczbf.com/tags/' + cj_from_php.tag_id + '/tag.js'
    } else {
        //url = cj_from_php.ajaxurl + '?action=cj_com_js'
        url = '/cj-proxy/tags/' + cj_from_php.tag_id + '/tag.js'
        //url = cj_from_php.proxy_sw_dir + 'tags/' + cj_from_php.tag_id + '/tag.js'
    }

    a=url;
    b=document;c='script';d=b.createElement(c);d.src=a;
    d.type='text/java'+c;d.async=true;
    d.id='cjapitag';

    a=b.getElementsByTagName(c)[0];a.parentNode.insertBefore(d,a)

    if (cj_from_php.sendOrderOnLoad){
        window.addEventListener('load', function buildAndSendOrder(){
        if (cj.order)
            cjApi.sendOrder(cj.order)
        });
    }

})();
