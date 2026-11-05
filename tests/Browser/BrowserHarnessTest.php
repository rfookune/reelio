<?php

it('blocks untrusted scripts and records CSP violations as JavaScript errors', function () {
    $page = visitWithCsp(route('home', absolute: false));

    $page->assertSee('Discover')->assertNoJavaScriptErrors();

    $page->script(<<<'JS'
        () => new Promise((resolve) => {
            document.addEventListener('securitypolicyviolation', resolve, { once: true });
            const script = document.createElement('script');
            script.textContent = 'window.untrustedScriptRan = true';
            document.body.appendChild(script);
        })
        JS);

    $page->assertScript('window.untrustedScriptRan === undefined')
        ->assertScript('window.__pestBrowser.jsErrors.some(error => error.message.startsWith("CSP violation: script-src-elem blocked inline"))');
});
