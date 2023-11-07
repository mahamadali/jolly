<?php

namespace Bones\Skeletons\Supporters;

use Bones\Str;
use Bones\Traits\Commander\AttrPairGenerator;

class BaseCodeTemplate
{
    use AttrPairGenerator;

    protected $template = '';
    protected $tab = "\t"; // tab indicator
    protected $ls = PHP_EOL; // line separator
    protected $extraAttrs;

    public function __construct($extraAttrs = [])
    {
        $this->extraAttrs = $extraAttrs;
    }

    public function signature()
    {
        $this->template .= '<?php' . $this->ls . $this->ls;
    }

    public function namespace($namespace)
    {
        if (!empty($namespace))
            $this->template .= 'namespace ' . $namespace . ';' . $this->ls . $this->ls;
    }

    public function use($use)
    {
        $this->template .= 'use ' . $use . ';' . $this->ls . $this->ls;
    }

    public function defineClass($class, $extends = null)
    {
        $extends = (!empty($extends)) ? ' extends ' . $extends : '';
        $this->template .= 'class ' . $class . $extends . $this->ls;
    }

    public function nl()
    {
        $this->template .= $this->ls;
    }

    public function tabs($count = 1)
    {
        return str_repeat($this->tab, $count);
    }

    public function line($line, $isStmt = true, $isLast = false)
    {
        $end_line_as = ($isStmt) ? ';' : '';
        $line_separator = (!$isLast) ? $this->ls : '';
        $this->template .= $line . $end_line_as . $line_separator;
    }

    public function tabLine($tabs, $line, $isStmt = true, $isLast = false)
    {  
        $tabs = (!empty($tabs)) ? $tabs : 1;
        $this->line($this->tabs($tabs) . $line, $isStmt, $isLast);
    }

    public function lastLine($line, $isStmt = true)
    {
        $this->line($line, $isStmt, true);
    }

    public function reset()
    {
        $this->template = '';
    }

    public function model($name, $namespace)
    {
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Models\Base\Model');
        $this->defineClass(Str::decamelize($name), 'Model');
        $this->line('{', false);
        if (!empty($this->extraAttrs)) {
            foreach ($this->extraAttrs as $extraAttr) {
                $attribute = explode('=', $extraAttr);
                $attrName = $attribute[0];
                if (Str::startsWith($attrName, '--') && count($attribute) > 0) {
                    $attrVal = $attribute[1];
                    $attrName = str_replace('--', '', $attrName);
                    $this->tabLine(1, "protected \$" . $attrName . " = '" . $attrVal . "'");
                }
            }
        }
        $this->nl();
        $this->line('}', false);
        return $this->template;
    }

    public function controller($name, $namespace)
    {
        $createAsModule = false;
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Bones\Request');
        if (!empty($this->extraAttrs)) {
            $attrPairs = $this->generateExtraAttrs([], $this->extraAttrs);
            if (!empty($attrPairs)) {
                if (array_key_exists('--module', $attrPairs)) {
                    $model = (array_key_exists('--model', $attrPairs) && !empty($attrPairs['--model'])) ? Str::decamelize(strtolower($attrPairs['--model'])) : Str::removeWords(Str::decamelize(strtolower($name)), ['controller']);
                    if (class_exists('Models\\'.$this->deCamelizedPath($model))) {
                        $this->use("Models\\" . $this->deCamelizedPath($model));
                        $createAsModule = true;
                        $model = $model . ' $' . strtolower($model);
                    } else {
                        $createAsModule = true;
                        $model = '$' . strtolower($model);
                    }
                }
            }
        }
        $this->defineClass(Str::decamelize($name));
        $this->line('{', false);
        $this->tabLine(1, "public function index(Request \$request)", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        if ($createAsModule) {
            $this->controllerAsModule($model);
        }
        $this->line('}', false);
        return $this->template;
    }

    public function controllerAsModule($model)
    {
        $this->tabLine(1, "public function create(Request \$request)", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
        $this->tabLine(1, "public function store(Request \$request)", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
        $this->tabLine(1, "public function show(Request \$request, ".$model.")", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
        $this->tabLine(1, "public function edit(Request \$request, ".$model.")", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
        $this->tabLine(1, "public function update(Request \$request, ".$model.")", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
        $this->tabLine(1, "public function destroy(Request \$request, ".$model.")", false);
        $this->tabLine(1, "{", false);
        $this->tabLine(2, "// Paint your jolly stuff with code...", false);
        $this->tabLine(1, "}", false);
        $this->nl();
    }

    public function view()
    {
        return "@php " . $this->ls . $this->tab . "// Paint your jolly stuff with code... " . $this->ls . "@endphp";
    }

    public function barrier($name, $namespace)
    {
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Bones\Request');
        $this->defineClass(Str::decamelize($name));
        $this->line('{', false);
        $this->tabLine(1, "public \$excludeRoutes = [", false);
        $this->tabLine(1, $this->tab . '// define routes to exclude from barrier check', false);
        $this->tabLine(1, ']');
        $this->nl();
        $this->tabLine(1, "public function check(Request \$request)", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, 'return true');
        if (!empty($this->extraAttrs)) {}
        $this->tabLine(1, '}', false);
        $this->line('}', false);
        return $this->template;
    }

    public function mailer($name, $namespace)
    {
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Contributors\Mail\Mailer');
        $this->defineClass(Str::decamelize($name), 'Mailer');
        $this->line('{', false);
        $this->tabLine(1, "protected \$data");
        $this->nl();
        $this->tabLine(1, "public function __construct(\$data)", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "\$this->data = \$data");
        $this->tabLine(1, '}', false);
        $this->nl();
        $this->tabLine(1, "public function prepare()", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "return \$this->html(content('mails/welcome', ['data' => \$this->data]))", false);
        $this->tabLine(5, "->to('recepient_address')", false);
        $this->tabLine(5, "->subject('subject')", false);
        $this->tabLine(5, "->attach('path/to/file', 'attachment_alias')");
        $this->tabLine(1, '}', false);
        if (!empty($this->extraAttrs)) {}
        $this->line('}', false);
        return $this->template;
    }

    public function texter($name, $namespace)
    {
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Contributors\SMS\Texter');
        $this->defineClass(Str::decamelize($name), 'Texter');
        $this->line('{', false);
        $this->tabLine(1, "protected \$data");
        $this->nl();
        $this->tabLine(1, "public function __construct(\$data)", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "\$this->data = \$data");
        $this->tabLine(1, '}', false);
        $this->nl();
        $this->tabLine(1, "public function prepare()", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "return \$this->template(content('sms/account-activated', ['data' => \$this->data]))", false);
        $this->tabLine(5, "->to('recepient_number')");
        $this->tabLine(1, '}', false);
        if (!empty($this->extraAttrs)) {}
        $this->line('}', false);
        return $this->template;
    }

    public function backgroundAction($name, $namespace)
    {
        $this->reset();
        $this->signature();
        $this->namespace($namespace);
        $this->use('Bones\Traits\Supporter\RunInBackground');
        $this->defineClass(Str::decamelize($name));
        $this->line('{', false);
        $this->tabLine(1, "use RunInBackground");
        $this->nl();
        $this->tabLine(1, "public function __construct(\$data)", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "\$this->data = \$data");
        $this->tabLine(1, '}', false);
        $this->nl();
        $this->tabLine(1, "public function prepare()", false);
        $this->tabLine(1, '{', false);
        $this->tabLine(2, "return \$this->data; // Prepare your logic here...", false);
        $this->tabLine(1, '}', false);
        if (!empty($this->extraAttrs)) {}
        $this->line('}', false);
        return $this->template;
    }

    public function setting($name)
    {
        $fn = 'setting'.ucfirst(strtolower($name));
        return $this->$fn();
    }

    public function settingApp()
    {
        $this->reset();
        $this->signature();
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "// Set base url of your project", false);
        $this->tabLine(1, "'base_url' => 'http://localhost',", false);
        $this->nl();
        $this->tabLine(1, "// Enter sub directory where your project is hosted or set it blank if your project is on host root", false);
        $this->tabLine(1, "'sub_dir' => 'jolly',", false);
        $this->nl();
        $this->tabLine(1, "// Set application stage (local || production", false);
        $this->tabLine(1, "'stage' => 'local',", false);
        $this->nl();
        $this->tabLine(1, "'title' => 'Jolly - A tiny PHP Framework',", false);
        $this->nl();
        $this->tabLine(1, "// Set default language for your application, this will be used when there is no language has been set or if any translation string is not", false);
        $this->tabLine(1, "// found then it will be returned from default_lang", false);
        $this->tabLine(1, "'default_lang' => 'en',", false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function settingAlias()
    {
        $this->reset();
        $this->signature();
        $this->use('Barriers\VerifyRequest');
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "// Add Barrier aliases to use as an alias", false);
        $this->tabLine(1, "'Barriers' => [", false);
        $this->tabLine(2, "'verify-request' => VerifyRequest::class,", false);
        $this->tabLine(1, '],', false);
        $this->nl();
        $this->tabLine(1, "'Form' => Contributors\Particles\Form::class,", false);
        $this->tabLine(1, "'Html' => Contributors\Particles\Html::class,", false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function settingDatabase()
    {
        $this->reset();
        $this->signature();
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "// Set this false if you do not want to use database", false);
        $this->tabLine(1, "'enable' => true,", false);
        $this->nl();
        $this->tabLine(1, "// Database details [ Key of this set is database name ]", false);
        $this->tabLine(1, "'database_name' => [", false);
        $this->tabLine(2, "'host' => 'localhost',", false);
        $this->tabLine(2, "'username' => 'root',", false);
        $this->tabLine(2, "'password' => '',", false);
        $this->tabLine(2, "'port' => 3306,", false);
        $this->tabLine(2, "'prefix' => '',", false);
        $this->tabLine(2, "'charset' => 'utf8',", false);
        $this->tabLine(2, "'socket' => null,", false);
        $this->tabLine(2, "'is_primary' => true,", false);
        $this->tabLine(1, '],', false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function settingSession()
    {
        $this->reset();
        $this->signature();
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "'age' => 14400, // seconds", false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function settingAlert()
    {
        $this->reset();
        $this->signature();
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "// Mail configuration", false);
        $this->tabLine(1, "'mail' => [", false);
        $this->nl();
        $this->tabLine(2, "'via' => 'smtp', // default | SMTP", false);
        $this->nl();
        $this->tabLine(2, "'from' => [", false);
        $this->tabLine(3, "'email' => 'admin@administration.com',", false);
        $this->tabLine(3, "'name' => 'Administration',", false);
        $this->tabLine(2, "],", false);
        $this->nl();
        $this->tabLine(2, "'reply' => [", false);
        $this->tabLine(3, "'email' => 'reply@administration.com',", false);
        $this->tabLine(3, "'name' => 'Administration',", false);
        $this->tabLine(2, "],", false);
        $this->nl();
        $this->tabLine(2, "'smtp' => [", false);
        $this->tabLine(3, "'host' => 'smtp.example.com',", false);
        $this->tabLine(3, "'username' => 'username',", false);
        $this->tabLine(3, "'password' => 'password',", false);
        $this->tabLine(3, "'port' => 465,", false);
        $this->tabLine(3, "'encryption' => 'tls', // SSL | TLS", false);
        $this->tabLine(3, "'debug' => false,", false);
        $this->tabLine(3, "'auth' => true,", false);
        $this->tabLine(2, "],", false);
        $this->nl();
        $this->tabLine(1, "],", false);
        $this->nl();
        $this->tabLine(1, "// SMS configuration", false);
        $this->tabLine(1, "'sms' => [", false);
        $this->nl();
        $this->tabLine(2, "'via' => 'twilio', // twilio", false);
        $this->nl();
        $this->tabLine(2, "'twilio' => [", false);
        $this->tabLine(3, "'account_sid' => 'TWILIO_ACCOUNT_SID',", false);
        $this->tabLine(3, "'auth_token' => 'TWILIO_AUTH_TOKEN',", false);
        $this->tabLine(3, "'from_number' => 'TWILIO_FROM_NUMBER',", false);
        $this->tabLine(3, "'api_endpoint' => 'https://api.twilio.com/2010-04-01/',", false);
        $this->tabLine(2, "],", false);
        $this->nl();
        $this->tabLine(1, "],", false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function settingTemplate()
    {
        $this->reset();
        $this->signature();
        $this->line("\$sysDefaultDir = 'defaults/'");
        $this->nl();
        $this->line('return [', false);
        $this->nl();
        $this->tabLine(1, "'404' => \$sysDefaultDir . '404',", false);
        $this->tabLine(1, "'503' => \$sysDefaultDir . '503',", false);
        $this->nl();
        $this->lastLine(']');
        return $this->template;
    }

    public function deCamelizedPath($uri)
    {
        $relativePathParts = explode('/', $uri);
        foreach ($relativePathParts as &$part) {
            $part = Str::decamelize($part);
        }

        return implode('/', $relativePathParts);
    }

}