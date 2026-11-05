<?php

function htmlRootTag(string $html): string
{
    preg_match('/<html[^>]*>/', $html, $matches);

    return $matches[0] ?? '';
}

test('pages render in the dark theme when no appearance is chosen', function () {
    $response = $this->get(route('home'))->assertOk();

    expect(htmlRootTag($response->getContent()))->toContain('class="dark"');
});

test('pages render in the dark theme when dark is chosen', function () {
    $response = $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('home'))
        ->assertOk();

    expect(htmlRootTag($response->getContent()))->toContain('class="dark"');
});

test('pages render without the dark theme when light or system is chosen', function (string $appearance) {
    $response = $this->withUnencryptedCookie('appearance', $appearance)
        ->get(route('home'))
        ->assertOk();

    expect(htmlRootTag($response->getContent()))->not->toContain('dark');
})->with(['light', 'system']);

test('the root view paints the charcoal background for each theme before styles load', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('background-color: #f4f4f5', false)
        ->assertSee('background-color: #101215', false);
});

test('an unrecognised appearance falls back to the dark theme and is never echoed into the page', function () {
    $response = $this->withUnencryptedCookie('appearance', "'</script><script>alert(1)</script>")
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('alert(1)', false);

    expect(htmlRootTag($response->getContent()))->toContain('class="dark"');
});
