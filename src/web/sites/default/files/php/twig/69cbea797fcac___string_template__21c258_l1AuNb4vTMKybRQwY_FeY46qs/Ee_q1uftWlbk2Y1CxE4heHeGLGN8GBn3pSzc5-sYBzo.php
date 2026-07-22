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

/* __string_template__21c25890d44f84efcce9f6f8ed6b539b */
class __TwigTemplate_ce0a004a6b1ede0bb72db1565708fda0 extends Template
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
        $_v0 = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            $context["deadline"] = $this->extensions['Twig\Extension\CoreExtension']->formatDate($this->extensions['Twig\Extension\CoreExtension']->modifyDate(($context["created"] ?? null), "+2 day"), "U");
            $context["mydate"] = $this->extensions['Twig\Extension\CoreExtension']->formatDate($this->extensions['Twig\Extension\CoreExtension']->convertDate(), "U");
            if ((($context["deadline"] ?? null) > ($context["mydate"] ?? null))) {
            } else {
                yield "<strong>This submission is now closed to further editing. Please note the submission editing deadline above. All current field values are visible, however, resubmission of this form is now disabled.</strong>";
            }
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(Twig\Extension\CoreExtension::spaceless($_v0));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["created"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "__string_template__21c25890d44f84efcce9f6f8ed6b539b";
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
        return array (  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "__string_template__21c25890d44f84efcce9f6f8ed6b539b", "");
    }
    
    public function checkSecurity()
    {
        static $tags = ["apply" => 1, "set" => 1, "if" => 1];
        static $filters = ["date" => 1, "date_modify" => 1, "spaceless" => 1];
        static $functions = ["date" => 1];

        try {
            $this->sandbox->checkSecurity(
                ['apply', 'set', 'if'],
                ['date', 'date_modify', 'spaceless'],
                ['date'],
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
