console.log('in the service worker')
let ajaxurl = decodeURIComponent( new URL(location).searchParams.get('ajaxurl') )

self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
  console.log('activated')
});

self.addEventListener('fetch', event => {
    console.log('fetching', event.request.url)
    url_obj = new URL(event.request.url)
  // this check isn't really needed since the service worker was limited in scope when registered
  if ( url_obj.pathname.includes('code-for-cj-affiliate-network/proxy') ) {

    console.log('request', event.request)
    let request = new Request(event.request)
    let new_url = ajaxurl + '?action=proxy&path=' + encodeURIComponent(url_obj.pathname + url_obj.search)
    request.url = new_url

    //console.log('proxied url', request.url, 'should be', ajaxurl + '?action=proxy&path=' + encodeURIComponent(url_obj.pathname + url_obj.search))
    console.log('new_url', new_url)

    return event.respondWith(fetch(new_url));
  }

  //throw new Error('The CJ service worker was asked to handle something that was out of scope')
});
