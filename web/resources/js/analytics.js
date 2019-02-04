var ref = window.document.getElementsByTagName('script')[0];
var script = window.document.createElement('script');
script.src = 'https://www.googletagmanager.com/gtag/js?id=UA-637620-33';
ref.parentNode.insertBefore(script, ref);

window.dataLayer = window.dataLayer || [];

function gtag() {
    dataLayer.push(arguments);
}

gtag('js', new Date());
gtag('config', 'UA-637620-33');