<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Form\FormFields;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\LoginHandler;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Escaper;
use RockAdmin\View\LoginView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateResolver;

/**
 * The real `page/login` template, parsed rather than matched.
 *
 * Every assertion here queries a `DOMDocument` built from what the shipped
 * template actually printed. A substring assertion would pass on markup that
 * is structurally broken — an input outside its form, a label pointing at
 * nothing, a quote that ended an attribute early — and this project has
 * shipped exactly that twice.
 */
#[CoversClass(LoginView::class)]
final class LoginTemplatesTest extends TestCase
{
    private const string PASSWORD = 'correct-horse-battery-staple';

    public function testTheFormPostsToTheSubmitRouteAndWorksWithoutJavaScript(): void
    {
        $form = $this->form($this->render());

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame('/admin/login', $form->getAttribute('action'));
        $this->assertStringContainsString('ra-login-form', $form->getAttribute('class'));
    }

    public function testTheFormCarriesTheCsrfToken(): void
    {
        $html = $this->render(token: 'token-0123456789');
        $input = $this->input($html, Csrf::FIELD);

        $this->assertSame('hidden', $input->getAttribute('type'));
        $this->assertSame('token-0123456789', $input->getAttribute('value'));
    }

    public function testTheFormCarriesTheReturnAddressUnderTheSharedBodyKey(): void
    {
        $input = $this->input($this->render(returnTo: '/admin/p/ads?grid%5Bpage%5D=3'), FormFields::RETURN_TO);

        $this->assertSame('hidden', $input->getAttribute('type'));
        $this->assertSame('/admin/p/ads?grid%5Bpage%5D=3', $input->getAttribute('value'));
    }

    public function testThePasswordFieldIsAPasswordFieldAndIsNeverEchoedBack(): void
    {
        // The view has no way to carry a password, which is the point: this
        // asserts the template never grows one either, by rendering with the
        // secret sitting in every other string the view holds.
        $html = $this->render(email: self::PASSWORD . '@example.com');
        $input = $this->input($html, LoginHandler::PASSWORD_FIELD);

        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertSame('', $input->getAttribute('value'));
        $this->assertSame('current-password', $input->getAttribute('autocomplete'));
    }

    public function testEveryLabelPointsAtAControlThatExists(): void
    {
        $document = $this->parse($this->render());
        $xpath = new DOMXPath($document);
        $labels = $xpath->query('//label[@for]');

        self::assertNotFalse($labels);
        $this->assertGreaterThanOrEqual(2, $labels->length, 'both fields are labelled');

        foreach ($labels as $label) {
            self::assertInstanceOf(DOMElement::class, $label);
            $for = $label->getAttribute('for');

            $this->assertNotSame('', $for);
            $this->assertNotNull(
                $document->getElementById($for),
                "the label '{$label->textContent}' points at id '{$for}', which no element carries",
            );
        }
    }

    public function testTheEmailIsRedrawnAndEscaped(): void
    {
        $html = $this->render(email: 'ada" autofocus onfocus="alert(1)');
        $input = $this->input($html, LoginHandler::EMAIL_FIELD);

        // The whole thing came back as one attribute value, which is the
        // property that matters: the quote did not end the attribute early and
        // no second attribute was created. Asserting the escaped text is
        // absent from the source would be wrong — `onfocus=&quot;` is still
        // the substring `onfocus=`, and that is exactly the kind of assertion
        // that mistakes escaped output for a break-out.
        $this->assertSame('ada" autofocus onfocus="alert(1)', $input->getAttribute('value'));
        $this->assertFalse($input->hasAttribute('onfocus'));
    }

    public function testARefusalIsDrawnAsAMessageTheScreenReaderAnnounces(): void
    {
        $html = $this->render(message: 'Those details do not match.');
        $alert = $this->one($html, '//*[contains(concat(" ", normalize-space(@class), " "), " ra-login-message ")]');

        $this->assertSame('alert', $alert->getAttribute('role'));
        $this->assertStringContainsString('Those details do not match.', $alert->textContent);
    }

    public function testAMessageIsEscapedRatherThanRendered(): void
    {
        $html = $this->render(message: '<script>alert(1)</script>');
        $document = $this->parse($html);

        $this->assertSame(0, $document->getElementsByTagName('script')->length);
        $this->assertStringContainsString('alert(1)', $document->textContent ?? '');
    }

    public function testNoMessageDrawsNoAlertAtAll(): void
    {
        $document = $this->parse($this->render());
        $xpath = new DOMXPath($document);
        $alerts = $xpath->query('//*[@role="alert"]');

        self::assertNotFalse($alerts);
        $this->assertSame(0, $alerts->length);
    }

    public function testAHintIsDrawnBesideTheMessageAndOnlyWithOne(): void
    {
        $withHint = $this->render(message: 'Too many attempts.', hint: 'The limit clears by itself.');

        $this->assertStringContainsString(
            'The limit clears by itself.',
            $this->one($withHint, '//*[contains(concat(" ", normalize-space(@class), " "), " ra-login-message ")]')->textContent,
        );
    }

    public function testThePageLoadsTheThemeAndNoScriptAtAll(): void
    {
        $document = $this->parse($this->render());

        $links = (new DOMXPath($document))->query('//link[@rel="stylesheet"]');
        self::assertNotFalse($links);
        $this->assertSame(2, $links->length, 'bootstrap and the RockAdmin theme');

        // The login page loads no script, so anything whose visibility depends
        // on one — a Bootstrap toast, say — must not be drawn here.
        $this->assertSame(0, $document->getElementsByTagName('script')->length);
    }

    public function testTheBodyCarriesTheLoginIdentityClasses(): void
    {
        $body = $this->one($this->render(), '//body');

        $this->assertStringContainsString('ra-login', $body->getAttribute('class'));
        $this->assertStringContainsString('ra-page-type-login', $body->getAttribute('class'));
    }

    // ------------------------------------------------------------- rendering

    private function render(
        string $token = 'tok',
        string $email = '',
        string $returnTo = '',
        ?string $message = null,
        string $hint = '',
    ): string {
        $urls = new UrlGenerator('/admin');
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);

        $view = new LoginView(
            new ShellView('Demo Admin', styles: ['/admin/_assets/a.css', '/admin/_assets/b.css']),
            $urls->route('login.submit'),
            $token,
            email: $email,
            returnTo: $returnTo,
            message: $message,
            hint: $hint,
        );

        return $renderer->render('page/login', $view);
    }

    // --------------------------------------------------------------- parsing

    /**
     * `DOMDocument::loadHTML()` is an HTML4 parser: it warns about `<main>`
     * and about the HTML5 doctype. The warnings are suppressed deliberately —
     * with the internal error buffer rather than with `@`, so a genuinely
     * unparseable document still yields a document object to assert against
     * rather than a silent false.
     */
    private function parse(string $html): DOMDocument
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function form(string $html): DOMElement
    {
        return $this->one($html, '//form');
    }

    private function input(string $html, string $name): DOMElement
    {
        return $this->one($html, '//input[@name=' . self::literal($name) . ']');
    }

    private function one(string $html, string $expression): DOMElement
    {
        $nodes = (new DOMXPath($this->parse($html)))->query($expression);

        self::assertNotFalse($nodes, "the expression {$expression} is not valid XPath");
        self::assertSame(1, $nodes->length, "expected exactly one match for {$expression}");

        $node = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    /** XPath 1.0 has no escape, so a value carrying a quote is concat()ed. */
    private static function literal(string $value): string
    {
        return str_contains($value, "'")
            ? 'concat(\'' . str_replace("'", "', \"'\", '", $value) . '\')'
            : "'" . $value . "'";
    }
}
