<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @olivero/../images/site-under-maintenance-icon.svg */
class __TwigTemplate_0001b28489dc6b83311d73af1c1ddcb6 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield "<svg class=\"maintenance-page-icon\" focusable=\"false\" width=\"170\" height=\"170\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M168 0a2 2 0 0 1 2 2v128.224c-14.273-14.681-25.439-26.986-25.439-26.986 3.544-6.715 7.974-41.186-15.061-64.465-23.035-23.279-54.044-19.25-59.36-17.012-4.253 1.791-4.43 6.715-2.658 8.954L99.82 63.843l-5.316 29.546-28.794 4.477-31.895-32.232c-4.252-4.298-7.678-.299-8.859 2.238-3.397 11.192-4.785 38.947 16.833 60.436 21.618 21.488 50.943 18.802 62.904 14.773L132.056 170H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h166z\" fill=\"#3D92C4\" fill-rule=\"nonzero\"/></svg>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@olivero/../images/site-under-maintenance-icon.svg";
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@olivero/../images/site-under-maintenance-icon.svg", "/srv/ccsa-website-iosdev/src/web/core/themes/olivero/images/site-under-maintenance-icon.svg");
    }
    
    public function checkSecurity()
    {
        static $tags = [];
        static $filters = [];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                [],
                [],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
