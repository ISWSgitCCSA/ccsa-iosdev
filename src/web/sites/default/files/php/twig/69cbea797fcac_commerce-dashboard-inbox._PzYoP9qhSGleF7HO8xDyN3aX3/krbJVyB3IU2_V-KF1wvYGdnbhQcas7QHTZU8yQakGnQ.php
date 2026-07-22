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

/* modules/contrib/commerce/templates/commerce-dashboard-inbox.html.twig */
class __TwigTemplate_eb5e9bc4d1a5c4ee0a9f52d83d838081 extends Template
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
        yield "<div class=\"commerce-dashboard--inbox\">
  <div class=\"inbox-header\">
    <h2 class=\"inbox-header__title\">";
        // line 3
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Inbox"));
        yield "</h2>
    ";
        // line 4
        if ((($tmp = ($context["unread_text"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 5
            yield "      <h4 class=\"inbox-header__unread-text\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["unread_text"] ?? null), "html", null, true);
            yield "</h4>
    ";
        }
        // line 7
        yield "  </div>
  <div class=\"inbox-message--wrapper card\">
  ";
        // line 9
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["messages"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["message"]) {
            // line 10
            yield "    <div class=\"inbox-message ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, \Drupal\Component\Utility\Html::getClass(CoreExtension::getAttribute($this->env, $this->source, $context["message"], "state", [], "any", false, false, true, 10)), "html", null, true);
            yield "\" data-message-id=\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, true, 10), "html", null, true);
            yield "\">
      <div class=\"inbox-message__status\">";
            // line 11
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["message"], "time_ago", [], "any", false, false, true, 11), "html", null, true);
            yield "<button type=\"button\" class=\"close\" aria-label=\"Close\"></button></div>
      <h6 class=\"inbox-message__subject\">";
            // line 12
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["message"], "subject", [], "any", false, false, true, 12), "html", null, true);
            yield "</h6>
      <div class=\"inbox-message__message\">";
            // line 13
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["message"], "message", [], "any", false, false, true, 13), "html", null, true);
            yield "</div>
      <div class=\"inbox-message__actions\">
        ";
            // line 15
            if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, $context["message"], "link", [], "any", false, false, true, 15))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 16
                yield "          ";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["message"], "link", [], "any", false, false, true, 16), "html", null, true);
                yield "
        ";
            }
            // line 18
            yield "        <a class=\"use-ajax message-dismiss action-link--danger\" href=\" ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->getPath("commerce.inbox_message.dismiss", ["message_id" => CoreExtension::getAttribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, true, 18)]), "html", null, true);
            yield "\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dismiss"));
            yield " </a>
      </div>
    </div>
  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['id'], $context['message'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 22
        yield "  ";
        if ((($tmp =  !($context["messages"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 23
            yield "    <div class=\"inbox-message__empty\">
      ";
            // line 24
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("No messages at the moment."));
            yield "
    </div>
  ";
        }
        // line 27
        yield "  </div>
  <div class=\"inbox-footer--wrapper\">
    <div class=\"inbox-footer\">
      <h5 class=\"inbox-footer__subject\">";
        // line 30
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Get the most out of Drupal Commerce."));
        yield "</h5>
      <div class=\"inbox-footer__message\">
        ";
        // line 32
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Learn how in the <a href=\"@docs\" target=\"_blank\">project documentation</a> and community support channels, or <a href=\"@support\" target=\"_blank\">hire the maintainers</a> for professional support or development.", ["@docs" => "https://docs.drupalcommerce.org", "@support" => "https://www.centarro.io/drupal-commerce/development-services"]));
        yield "
      </div>
    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["unread_text", "messages"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/commerce/templates/commerce-dashboard-inbox.html.twig";
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
        return array (  131 => 32,  126 => 30,  121 => 27,  115 => 24,  112 => 23,  109 => 22,  96 => 18,  90 => 16,  88 => 15,  83 => 13,  79 => 12,  75 => 11,  68 => 10,  64 => 9,  60 => 7,  54 => 5,  52 => 4,  48 => 3,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/commerce/templates/commerce-dashboard-inbox.html.twig", "/srv/ccsa-website-iosdev/src/web/modules/contrib/commerce/templates/commerce-dashboard-inbox.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 4, "for" => 9];
        static $filters = ["t" => 3, "escape" => 5, "clean_class" => 10];
        static $functions = ["path" => 18];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'for'],
                ['t', 'escape', 'clean_class'],
                ['path'],
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
