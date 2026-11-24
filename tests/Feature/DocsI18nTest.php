<?php

declare(strict_types=1);

/**
 * A reader who picks a language gets the whole docs site in it: the content,
 * the sidebar, page titles, meta tags and the site's own labels — and keeps
 * getting it on plain links, because the choice is remembered in a cookie.
 */

use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Support\DocLocales;
use Magna\Docs\Support\DocTree;
use Magna\Testing\PluginTestCase;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');
    // Plugin routes register after boot in tests; refresh the name lookups.
    app('router')->getRoutes()->refreshNameLookups();
});

/**
 * Guides (ml: ഗൈഡുകൾ) → Introduction (ml), Installation (ml), English Only.
 * Introduction also has a child page with an ml translation.
 *
 * @return array{intro: DocPage, install: DocPage, english: DocPage, child: DocPage, guides: DocCollection}
 */
function docsI18nSeed(): array
{
    $guides = DocCollection::create(['title' => 'Guides', 'slug' => 'guides', 'order' => 0, 'is_public' => true]);
    $guides->translations()->create(['locale' => 'ml', 'title' => 'ഗൈഡുകൾ']);

    $intro = DocPage::create([
        'collection_id' => $guides->id,
        'title' => 'Introduction',
        'slug' => 'intro',
        'excerpt' => 'Start here.',
        'meta_title' => 'Intro | Acme Docs',
        'meta_description' => 'English description.',
        'content' => "## Setup\n\nEnglish body.",
        'status' => 'published',
        'order' => 0,
    ]);
    $intro->translations()->create([
        'locale' => 'ml',
        'title' => 'ആമുഖം',
        'excerpt' => 'ഇവിടെ തുടങ്ങൂ.',
        'meta_title' => 'ആമുഖം | ഡോക്സ്',
        'meta_description' => 'മലയാളം വിവരണം.',
        'content' => "## സജ്ജീകരണം\n\nമലയാളം ഉള്ളടക്കം.",
    ]);

    $install = DocPage::create([
        'collection_id' => $guides->id,
        'title' => 'Installation',
        'slug' => 'install',
        'content' => 'Install body.',
        'status' => 'published',
        'order' => 1,
    ]);
    $install->translations()->create(['locale' => 'ml', 'title' => 'ഇൻസ്റ്റാളേഷൻ', 'content' => 'ഇൻസ്റ്റാൾ ചെയ്യുക.']);

    $english = DocPage::create([
        'collection_id' => $guides->id,
        'title' => 'English Only',
        'slug' => 'english-only',
        'content' => 'Only English here.',
        'status' => 'published',
        'order' => 2,
    ]);

    $child = DocPage::create([
        'collection_id' => $guides->id,
        'parent_id' => $intro->id,
        'title' => 'First Steps',
        'slug' => 'first-steps',
        'content' => 'Child body.',
        'status' => 'published',
        'order' => 0,
    ]);
    $child->translations()->create(['locale' => 'ml', 'title' => 'ആദ്യ പടികൾ', 'content' => 'കുട്ടി.']);

    return compact('intro', 'install', 'english', 'child', 'guides');
}

// ── Locale resolution and the cookie ─────────────────────────────────────────

it('renders English by default and sets no language cookie', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro')
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('English body.')
        ->assertDontSee('മലയാളം ഉള്ളടക്കം')
        ->assertCookieMissing('docs_lang');
});

it('renders the translation for ?lang and remembers the choice for a year', function (): void {
    docsI18nSeed();

    $response = $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertSee('ആമുഖം')
        ->assertSee('മലയാളം ഉള്ളടക്കം')
        ->assertDontSee('English body.')
        ->assertCookie('docs_lang', 'ml');

    $cookie = $response->getCookie('docs_lang', false);
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(364)->getTimestamp())
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->addDays(366)->getTimestamp());
});

it('keeps plain links in the remembered language', function (): void {
    docsI18nSeed();

    $this->withCookie('docs_lang', 'ml')
        ->get('/docs/install')
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertSee('ഇൻസ്റ്റാൾ ചെയ്യുക.')
        // A plain link re-uses the cookie; it does not re-issue it.
        ->assertCookieMissing('docs_lang');

    // The docs home (first page in sidebar order) follows it too.
    $this->withCookie('docs_lang', 'ml')
        ->get('/docs')
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertSee('മലയാളം ഉള്ളടക്കം');
});

it('falls back to English for a page without the reader\'s language', function (): void {
    docsI18nSeed();

    $this->withCookie('docs_lang', 'ml')
        ->get('/docs/english-only')
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Only English here.')
        ->assertSee('Guides');

    // An explicit ?lang the page lacks also gets English, and is still remembered.
    $this->get('/docs/english-only?lang=ml')
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertCookie('docs_lang', 'ml');
});

it('switches back to English with ?lang=en, which the English menu item links to', function (): void {
    docsI18nSeed();

    $this->withCookie('docs_lang', 'ml')
        ->get('/docs/intro')
        ->assertOk()
        ->assertSee('href="http://localhost/docs/intro?lang=en"', false)
        ->assertSee('href="http://localhost/docs/intro?lang=ml"', false);

    $this->withCookie('docs_lang', 'ml')
        ->get('/docs/intro?lang=en')
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('English body.')
        ->assertCookie('docs_lang', 'en');
});

it('ignores a malformed ?lang and falls through to the cookie', function (): void {
    docsI18nSeed();

    $this->withCookie('docs_lang', 'ml')
        ->get('/docs/intro?lang='.urlencode('"><script>'))
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertCookieMissing('docs_lang');

    // Case is normalised.
    $this->get('/docs/intro?lang=ML')
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertCookie('docs_lang', 'ml');
});

it('marks the page as varying by cookie, since the cookie picks its language', function (): void {
    docsI18nSeed();

    $response = $this->get('/docs/intro')->assertOk();

    expect((string) $response->headers->get('Vary'))->toContain('Cookie')
        // ...and nothing marks it publicly cacheable in the first place.
        ->and((string) $response->headers->get('Cache-Control'))->toContain('private');
});

it('remembers ?lang on the empty docs home too', function (): void {
    $this->get('/docs?lang=ml')
        ->assertOk()
        ->assertSee('<html lang="ml"', false)
        ->assertCookie('docs_lang', 'ml');
});

// ── hreflang and canonical ───────────────────────────────────────────────────

it('links every language of a page with hreflang plus x-default', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/docs/intro">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/docs/intro">', false)
        ->assertSee('<link rel="alternate" hreflang="ml" href="http://localhost/docs/intro?lang=ml">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/docs/intro">', false);

    // The translated view is canonical at its ?lang address.
    $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/docs/intro?lang=ml">', false)
        ->assertSee('<meta property="og:url" content="http://localhost/docs/intro?lang=ml">', false);
});

it('emits no hreflang links for a page with no translations', function (): void {
    docsI18nSeed();

    $this->get('/docs/english-only')
        ->assertOk()
        ->assertDontSee('hreflang=', false);
});

it('declares og:locale for the view and og:locale:alternate for the other languages', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('<meta property="og:locale" content="ml_IN">', false)
        ->assertSee('<meta property="og:locale:alternate" content="en_GB">', false);
});

// ── Translated chrome ────────────────────────────────────────────────────────

it('uses the translation\'s own meta title, description and excerpt', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('<title>ആമുഖം | ഡോക്സ്</title>', false)
        ->assertSee('<meta name="description" content="മലയാളം വിവരണം.">', false)
        ->assertSee('ഇവിടെ തുടങ്ങൂ.')
        ->assertDontSee('English description.')
        ->assertDontSee('Start here.');
});

it('builds the meta title from the translated title when the translation has none', function (): void {
    docsI18nSeed();

    $this->get('/docs/install?lang=ml')
        ->assertOk()
        ->assertSee('<title>ഇൻസ്റ്റാളേഷൻ — Documentation</title>', false)
        // No translated excerpt: no lead paragraph, and never the English page's.
        ->assertDontSee('Start here.');
});

it('translates the sidebar, prev/next and breadcrumb, falling back to English per page', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('ഗൈഡുകൾ')               // collection heading
        ->assertSee('ഇൻസ്റ്റാളേഷൻ')          // sibling page title (sidebar + Next)
        ->assertSee('ആദ്യ പടികൾ')            // child page title
        ->assertSee('English Only')          // no ml translation: English title
        ->assertDontSee('>Guides<', false);

    // Breadcrumb trail of a child page uses the translated parent title.
    $this->get('/docs/first-steps?lang=ml')
        ->assertOk()
        ->assertSee('<a href="http://localhost/docs/intro" class="hover:text-brand transition-colors">ആമുഖം</a>', false);
});

it('renders the site\'s own labels in the page\'s language', function (): void {
    docsI18nSeed();

    // Stand-in translations: the shipped ml file starts out as English.
    app('translator')->addLines([
        'docs.on_this_page' => 'ഈ പേജിൽ',
        'docs.helpful_question' => 'ഈ പേജ് സഹായകമായോ?',
        'docs.next' => 'അടുത്തത് →',
        'docs.copy' => 'പകർത്തുക',
    ], 'ml', 'docs');

    $this->get('/docs/intro?lang=ml')
        ->assertOk()
        ->assertSee('ഈ പേജിൽ')
        ->assertSee('ഈ പേജ് സഹായകമായോ?')
        ->assertSee('അടുത്തത് →')
        // Script strings are JSON-encoded into the page.
        ->assertSee(trim((string) json_encode('പകർത്തുക'), '"'), false)
        // A key the language does not have falls back to English.
        ->assertSee('Thanks for your feedback!');

    $this->get('/docs/intro')
        ->assertOk()
        ->assertSee('Was this page helpful?')
        ->assertDontSee('ഈ പേജിൽ');
});

it('ships every language file with exactly the English keys', function (): void {
    $base = dirname(__DIR__, 2).'/resources/lang';
    $english = array_keys(require $base.'/en/docs.php');

    foreach (['hi', 'bn', 'gu', 'kn', 'ml', 'mr', 'ta', 'te'] as $locale) {
        $file = $base.'/'.$locale.'/docs.php';
        expect(is_file($file))->toBeTrue()
            ->and(array_keys(require $file))->toBe($english);
    }
});

it('counts reading time in any script', function (): void {
    $page = DocPage::create([
        'title' => 'Long', 'slug' => 'long', 'content' => 'Short English.', 'status' => 'published', 'order' => 0,
    ]);
    $page->translations()->create(['locale' => 'ml', 'title' => 'നീളം', 'content' => str_repeat('വാക്ക് ', 450)]);

    $this->get('/docs/long?lang=ml')->assertOk()->assertSee('2 min read');
    $this->get('/docs/long')->assertOk()->assertSee('1 min read');
});

it('opens the language menu items with a full page load', function (): void {
    docsI18nSeed();

    $this->get('/docs/intro')
        ->assertOk()
        ->assertSee('role="menuitem" data-no-spa', false);
});

// ── Native language names ────────────────────────────────────────────────────

it('names each language in its own script, with the English name alongside for admins', function (): void {
    expect(DocLocales::label('hi'))->toBe('हिन्दी')
        ->and(DocLocales::label('bn'))->toBe('বাংলা')
        ->and(DocLocales::label('gu'))->toBe('ગુજરાતી')
        ->and(DocLocales::label('kn'))->toBe('ಕನ್ನಡ')
        ->and(DocLocales::label('ml'))->toBe('മലയാളം')
        ->and(DocLocales::label('mr'))->toBe('मराठी')
        ->and(DocLocales::label('ta'))->toBe('தமிழ்')
        ->and(DocLocales::label('te'))->toBe('తెలుగు')
        ->and(DocLocales::label('en'))->toBe('English')
        ->and(DocLocales::englishName('ml'))->toBe('Malayalam')
        ->and(DocLocales::adminLabel('ml'))->toBe('മലയാളം (Malayalam)')
        ->and(DocLocales::adminLabel('en'))->toBe('English')
        ->and(DocLocales::flag('ml'))->toBe('🇮🇳')
        ->and(DocLocales::ogLocale('ml'))->toBe('ml_IN')
        ->and(DocLocales::ogLocale('en'))->toBe('en_GB');

    docsI18nSeed();

    $this->get('/docs/intro')
        ->assertOk()
        ->assertSee('മലയാളം')
        ->assertSee('title="Malayalam"', false);
});

// ── Search ───────────────────────────────────────────────────────────────────

it('searches the reader\'s language and answers with translated titles and snippets', function (): void {
    docsI18nSeed();

    // Matches only the Malayalam content.
    $this->getJson('/docs/search?q='.urlencode('ഉള്ളടക്കം').'&lang=ml')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.title', 'ആമുഖം')
        ->assertJsonPath('0.excerpt', 'ഇവിടെ തുടങ്ങൂ.')
        ->assertJsonPath('0.url', 'http://localhost/docs/intro');

    // English matches still count, but come back in the reader's language
    // where the page has it — and in English where it does not.
    $results = $this->getJson('/docs/search?q=English&lang=ml')->assertOk()->json();
    $titles = array_column($results, 'title', 'slug');
    expect($titles)->toHaveKey('intro')
        ->and($titles['intro'])->toBe('ആമുഖം')
        ->and($titles['english-only'])->toBe('English Only');

    // In English, the Malayalam text is not searched.
    $this->getJson('/docs/search?q='.urlencode('ഉള്ളടക്കം'))
        ->assertOk()
        ->assertJsonCount(0);

    // The cookie stands in for ?lang (a same-origin fetch sends it).
    $this->withCredentials()
        ->withCookie('docs_lang', 'ml')
        ->getJson('/docs/search?q='.urlencode('ഉള്ളടക്കം'))
        ->assertOk()
        ->assertJsonCount(1);
});

it('ranks a translated title hit first', function (): void {
    docsI18nSeed();

    $results = $this->getJson('/docs/search?q='.urlencode('ഇൻസ്റ്റാ').'&lang=ml')->assertOk()->json();

    expect($results[0]['slug'])->toBe('install');
});

// ── Sidebar tree cache ───────────────────────────────────────────────────────

it('caches the sidebar per locale and drops it the moment a title changes', function (): void {
    ['guides' => $guides, 'install' => $install] = docsI18nSeed();

    $english = DocTree::build();
    $malayalam = DocTree::build('ml');

    expect($english[0]['title'])->toBe('Guides')
        ->and($malayalam[0]['title'])->toBe('ഗൈഡുകൾ');

    // Same second, through Eloquent: the version bump invalidates.
    $guides->translations()->where('locale', 'ml')->first()?->update(['title' => 'വഴികാട്ടികൾ']);
    $install->translations()->where('locale', 'ml')->first()?->update(['title' => 'സ്ഥാപിക്കൽ']);

    $titles = array_column(DocTree::flatPages(DocTree::build('ml')), 'title', 'slug');

    expect(DocTree::build('ml')[0]['title'])->toBe('വഴികാട്ടികൾ')
        ->and($titles['install'])->toBe('സ്ഥാപിക്കൽ')
        ->and(DocTree::build()[0]['title'])->toBe('Guides');

    $this->get('/docs/intro?lang=ml')->assertOk()->assertSee('വഴികാട്ടികൾ');
});
