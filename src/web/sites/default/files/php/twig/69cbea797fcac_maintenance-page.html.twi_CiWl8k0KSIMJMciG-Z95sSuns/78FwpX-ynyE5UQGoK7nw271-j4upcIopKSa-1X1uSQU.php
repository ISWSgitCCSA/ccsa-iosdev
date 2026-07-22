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

/* core/themes/olivero/templates/maintenance-page.html.twig */
class __TwigTemplate_b25aacdc55eb66799a76c40659d670b1 extends Template
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
        // line 11
        yield "
<div id=\"page-wrapper\" class=\"page-wrapper\">
  <div id=\"page\">

    <header id=\"header\" class=\"site-header\" role=\"banner\" data-once=\"navigation\">
      <div class=\"site-header__fixable\">
        <div class=\"site-header__initial\">
        </div>
        <div id=\"site-header__inner\" class=\"site-header__inner\">
          <div class=\"container site-header__inner__container\">
            <div class=\"site-branding block block-system block-system-branding-block\">
              <div class=\"site-branding__inner\">
                <div class=\"site-branding__text\">
                  <div class=\"site-branding__name\">
                    ";
        // line 25
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["site_name"] ?? null), "html", null, true);
        yield "
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div id=\"main-wrapper\" class=\"layout-main-wrapper layout-container\">
      <div id=\"main\" class=\"layout-main\">
        <div class=\"main-content\">
          <a id=\"main-content\" tabindex=\"-1\"></a>
          <div class=\"main-content__container container\">
            ";
        // line 40
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 40), "html", null, true);
        yield "
            <main role=\"main\">
              <div class=\"region region--content-above grid-full layout--pass--content-medium\">
                ";
        // line 43
        if ((($tmp = ($context["title"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 44
            yield "                  <h1 class=\"title\" id=\"page-title\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title"] ?? null), "html", null, true);
            yield "</h1>
                ";
        }
        // line 46
        yield "              </div>
              <div class=\"region region--content grid-full layout--pass--content-medium\" id=\"content\">
                <div id=\"block-olivero-content\" class=\"block block-system block-system-main-block text-content\">
                  ";
        // line 49
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 49), "html", null, true);
        yield "

                  ";
        // line 51
        yield from $this->load("@olivero/../images/site-under-maintenance-icon.svg", 51)->unwrap()->yield($context);
        // line 52
        yield "                </div>
              </div>
            </main>
          </div>
        </div>
        <div class=\"social-bar\">
        </div>
      </div>
    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["site_name", "page", "title"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/themes/olivero/templates/maintenance-page.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  104 => 52,  102 => 51,  97 => 49,  92 => 46,  86 => 44,  84 => 43,  78 => 40,  60 => 25,  44 => 11,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/themes/olivero/templates/maintenance-page.html.twig", "/srv/ccsa-website-iosdev/src/web/core/themes/olivero/templates/maintenance-page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 43, "include" => 51];
        static $filters = ["escape" => 25];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'include'],
                ['escape'],
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
