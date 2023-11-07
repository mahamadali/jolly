# Jolly

`A tiny PHP Framework`

## Installation

Clone this repository to your system and start using it ðŸ‘

```bash
git clone https://wisencode@bitbucket.org/wisencode/jolly.git
```

### Set up fresh config files
```bash
php commander set:config
```

### Config Note

``In settings/app.php, set your config variables. Do not forget to set SUB_DIR if you are not running your project on root of the server``

### Routing

```bash
// Set GET route for /home
Router::get(['/home'], [ WelcomeController::class, 'index' ]);
```

```bash
# Set GET route for multiple prefixes. Below route will be accessible for both yourproject.com/home and yourproject.com/
Router::get(['/home', '/'], [ WelcomeController::class, 'index' ]);
```

```bash
# Set name to route
Router::get(['/home'], [ WelcomeController::class, 'index' ])->name('homepage');
```

```bash
## Add barrier to the route. 

## Note: Barrier is like check/middleware before route is accessible
Router::get(['/home', '/'], [ WelcomeController::class, 'index' ])->name('landing')->barrier('verify-token');
// OR
Router::get(['/home', '/'], [ WelcomeController::class, 'index' ])->name('landing')->barrier(VerifyToken::class);
# Note : VerifyToken Barrier class will return true or false for proceeding next
```

```bash
// Multiple Barriers 
Router::get(['/home', '/'], [ WelcomeController::class, 'index' ])->name('landing')->barrier([VerifyToken::class, VerifyAPIKey::class]);
// OR
Router::get(['/home', '/'], [ WelcomeController::class, 'index' ])->name('landing')->barrier([VerifyToken::class, 'verify-api-key']);
```

```bash
# Barrier aliases can be set in settings/aliases.php for Barriers array

return [
    'Barriers' => [
        'verify-token' => VerifyToken::class,
    ]
];
```

```bash
### Route Bunch

Router::bunch('/user', ['barrier' => ['verify-token'], 'as' => 'user.'], function() {
    Router::get('/list', [ UserController::class, 'index' ])->name('list')->withoutBarrier('verify-token');
    Router::get('/create', [ UserController::class, 'create' ])->name('create');
    Router::post('/store', [ UserController::class, 'store' ])->name('store');
});

# Note : withoutBarrier can be applied on specific route to skip sepecific barrier check on route. withoutBarrier() accepts string (alias of barrier), array (alias or class of barriers)
```

```bash
### Dynamic parameters binding

Router::bunch('/post', ['as' => 'post.'], function() {
    Router::get('/{posted-by}/{?title}', [ WelcomeController::class, 'page' ])->name('details')->where('posted-by', '[0-9]+');
});

# In above route {posted-by} is mendatory attribute but title is optional attribute
# Regix can be set with ->where($dynamic_attr, 'REGEX EXPRESSION') on any dynamic attribute to validate dynamic urls

Note : You can create seperate files (Ex - user.php, admin.php, api.php etc.) as per your need in segments/routes to formalize your project routes and it will be automatically loaded in the project

## SUPOPRTED METHODS ARE 'ANY', 'GET', 'PUT', 'POST', 'DELETE', 'PATCH', 'UPDATE', 'OPTIONS'
```

### Rendering view 

``` php
class UserController {
    public function index() 
    {
        // It will load segments/views/user/home.jly.php
        return render('user/home');
    }
}

# Passing additional data to view

return render('user/home')->with(['greet' => 'Welcome to Jolly World!']);
```

### Redirecting

```php
return redirect(route('user.home'))->go();

# Redirect with flash data
return redirect(route('user.home'))->withUser($user)->go();

# Redirect with status
return redirect(route('user.home'), 301)->withUser($user)->go();

# Redirect back
return redirect()->withUser($user)->back();
```

### Set/Get Flash Data
```bash
# Set flash
Session::setFlash('type', 'text');
// OR
session()->setFlash('type');

# Get flash
Session::flash('type');
// OR
session()->flash('type');
```

### Session
```php
#Set session data
Session::set('key', 'value'); OR session()->set('key', 'value');

#Get session data
Session::get('key'); OR session()->get('key');

# Set collection in session
Session::appendSet('key', 'array|object'); OR session()->appendSet('key', 'array|object');

# Remove session variable
Session::remove('key'); OR session()->remove('key');

# Check session has key
Session::has('key'); OR session()->has('key');

#Check session has flash
Session::hasFlash('type'); OR session()->hasFlash('type');

# Clear session
Session::clear(); OR session()->clear();
```

### Multi langual support

```bash
Session::setLanguage('en'); or session()->setLanguage('en');

# Usage: 

session()->setLanguage('en');
$translated = trans('built_n_managing'); #Here translated word will be returned from translations/en.php

# Dynamic translations

--locker
     --translations
                --en.php
                
    return [
        'welcome_user_with_role' => 'Welcome {{user}}, You entered with {{role}} role',
    ];
    
# Usage

     $trans = trans('welcome_user_with_role', [
        'user' => 'Akbarhusen G. Maknojiya',
        'role' => 'Visitor',
    ]);
                

# Default platform language will be taken from 'default_lang' attribute from settings/app.php
````

### Request

```php
Bones\Request will be accessible on each route with request data

public function save(Request $request) 
{
    $formData = $request->all();
}

// Set custom msgs for validation rules. If not provided then default system messages will be returned on error
$errorMessages = [
    'name.required' => 'Name must not be empty',
    'email.unique' => 'Email already exists in the system',
];

# Request can be validated with validate method
$validate = $request->validate([
    'name' => ['required', 'max:10'],
    'user_name' => ['required', 'max:10'],
    'email' => 'required|max:10|email|unique:users,email',
    'password' => ['required', 'min:6', 'max:12', 'eqt:confirm_password'],
], $errorMessages);

if ($validate->hasError()) {
    return redirect(route('user.home'))->with(['errors' => $validate->errors()])->go();
}

// Check for the files

if ($file = $request->hasFile('fileinput') {
    $fileMimeType = $request->files('fileinput')->mimeType;
    $fileFileSize = $request->files('fileinput')->fileSize;
    $fileOrigName = $request->files('fileinput')->origName;
    $fileExtension = $request->files('fileinput')->extension;
    if ($request->files('fileinput')->isImage()) {
        $fileDimensions = $request->files('fileinput')->dimensions;
    }
    
    // Saving file
    $request->files('fileinput')->save($destination);
    
    // Saving file with custom name
    $request->files('fileinput')->save($destination, $uploadAs);
    
}
```

```bash
# Get current page in request
request()->currentPage();

# Retrieve request parameter
request()->get('formElement');

# Retrieve specific parameter(s) [Returns Array]
request()->getOnly(string|array);
>> request()->getOnly('username'); OR request()->getOnly(['first_name', 'last_name', 'username', 'password']);

# Retrieve parameters but exclude specific parameter(s) [Returns Array]
request()->except(string|array);
>> request()->except('username'); OR request()->except(['first_name', 'last_name']);
```

### Model
```php
namespace Models;

use Models\Base;
use Models\Post;

class User extends Base
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    
    protected $elements = [
        'name',
        'email',
        'password',
        'is_deleted'
    ];
    
    protected $defaults = [
        'is_deleted' => 0,
    ];
    
    public function getAvatarProperty()
    {
        return 'https://i2.wp.com/ui-avatars.com/api/'.urlencode($this->name).'/64?ssl=1';
    }
    
    public function posts() 
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}
```

## Model Properties
```php
// Database table to bind with. If not provided then from model name with snake-case and pluralize format $table would be default value
$table = ''; 

// Primary key of table. If not provided then `id` would be default column for primaryKey
$primaryKey = ''; 

// Elements (Columns) which needs to be used as a set while adding directly from array in create() method
protected $elements = [];

// Set default values to specific columns
protected $defaults = [];

// Attach relational data with the object itself
protected $with = [];

// Attach properties the object itself
protected $attaches = [];

// Hide properies on the object
protected $hidden = [];

```

```php
### Create dynamic properties for model object
public function getDynamicProperty() {
    return 'I am ' . $this->dynamic;
}
# And above will be accessible on model object like $user->dynamic;
```

### Custom function(s) on model object

```php
public function posts() {
    return Post::where('posted_by', $this->id)->get();
}
# And above will be accessible on model object like $user->posts();
```

### Relational data on model object

```php
public function posts() {
    return $this->hasMany(Post::class, 'user_id', 'id');
}
# And above will be accessible on model object like $user->posts();

# Usage of above relational function

User::with('posts')->orderBy('id')->get(); // This will return all users in descending order with associated posts for each user object matching columns given in then defination of relation
```

### Relations Types

```php
parallelTo() - // Returns one result if matches with the columns given in the defination
hasMany() - // Returns multiple results if matches with the columns given in the defination
hasOne() - // Returns one result if matches with the columns given in the defination
hasManyVia() - // Returns multiple results if matches with the columns given in the defination
hasOneVia() - // Returns one result if matches with the columns given in the defination
```

### Model database functions
```php
# Retrieve row by primary key
$model->find('id'); // It will try to find row as per the primary key set for model

# Retrieve one row

// If no column provided then it will return all columns
$model->getOne();
// OR
$model->where('column', 'value')->getOne('column_1, column_2');
// OR
$model->where('column', 'value')->orWhere('column', 'value')->getOne();

# Retrieve results
$model->get(); // Returns all rows
$model->get('number_of_rows', 'columns');
> $model->get(10, 'name, email'); // This will return 10 rows with name and email columns only

# Retrieve value of column from one row
$model->getValue('column');
> $model->where('email', 'test@example.com')->getValue('username'); // This will return username from first row table where email will be test@example.com
```

# Model response formats
```php
> $model->where('is_active', 1)->getAsJson();
> $model->where('is_active', 1)->getAsObject();
> $model->where('is_active', 1)->getAsArray();

> $model->where('is_active', 1)->getOneAsJson();
> $model->where('is_active', 1)->getOneAsObject();
> $model->where('is_active', 1)->getOneAsArray();
```

```php
# Where conditions
$model->where('column', 'value', 'operator', 'cond'); // Default operator is = and default cond is AND
> $model->where('name', 'jolly%', 'LIKE'); // Like
> $model->whereLike('name', 'value');

> $model->where('name', 'jolly%', 'NOT LIKE'); // NOT Like
> $model->whereNotLike('name', 'value');

> $model->where('points', '200', '>='); // Conditional
> $model->where('points', [200, 100], 'IN'); // Where in
> $model->where('points', [200, 100], 'NOT IN'); // Where not in
> $model->where('points', [200, 100], '!='); // Where not equalt to
> $model->where('points', 10, '>=')->where('points', 20, '<='); // Where >=10 and <=20
> $model->where('id', [4, 20], 'BETWEEN'); // Where between 4 and 20
> $model->where('id', [4, 20], 'NOT BETWEEN'); // Where not between 4 and 20

# pluck specific columns
> $model->where('cat_id', '2')->pluck(['id', 'name']);

# create entry
> $model->create([
    'title' => 'Jolly',
    'description' => 'Tiny PHP Framework'
]);
> $model->create(request()->all());

# create entry with insert()
> $model->insert([
    'title' => 'Jolly',
    'description' => 'Tiny PHP Framework'
]);

# create multiple entries with insertMulti()
> $model->insertMulti([
    [
        'title' => 'Jolly',
        'description' => 'Tiny PHP Framework'
    ],
    [
        'title' => 'Addiotional Jolly',
        'description' => 'Additional Tiny PHP Framework'
    ],
]);

# Update entry
> $model->where('id', 22)->update([
    'title' => 'Uograded Jolly'
]);

# Delete entry
$model->where('id', 22)->delete();
```

### View (.jly File Format)

```html
@extends('app')

@block("title"){{ 'User Home' }}@endblock

@block("styles")
<style type="text/css">
    div {
            margin: 0 auto;
            justify-content: center;
            text-align: center;
    }
    a {
            background-color: blue;
            color:#fff;
            text-decoration:none;
            padding: 10px;
            margin-top: 2em;
            display: inline-block;
    }
    </style>
@endblock

@block("content")
        <div>
                <a href="{{ route('user.create') }}">Create User</a>
                @if (session()->has('user')):
                       {{ session()->user()->name.' is just registered!' }}
                @endif
        </div>
@endblock

@block("scripts")
    <script src="{{ url('assets/js/app.js') }}" type="text/javascript"></script>
@endblock

<!-- Above view is extending app.jly.php -->

# app.jly.php (It will serve as master file to plot and plant the view files)

<html>
    <head>
        <title>@plot('title')</title>
        <meta charset="utf-8">
        @plot('styles')
    </head>
    <body>
        @plot('content')
        @plot('scripts')
    </body>
</html>
```

# Syntaxes for view (.jly) file
```php 
// Foreach loop
@foreach ($collections as $collection):
// Do your jolly stuff
@endforeach

// For loop
@for ($i = 0; $i <10; $i++):
// Do your jolly stuff
@endfor

// While loop
$count = 10;
@while($count <= 0): {
    $count--;
}

// Echoing
<h1>How are your {{ $user->name  }}</h1>

// Echoing htmlstrings
{{{ $pages->contact_us  }}}
```

### setting() function to get setting file variables
```php
echo setting('{filename}.{variable}');

Example

// Below statement will try to get the variable from settings/app.php.
echo setting('app.title');

// Get setting variable with default value if given variable not found in setting file
echo setting('app.title', 'Welcome to Jolly Framework!');
```

# commander (CLI usage)

```bash
# List routes
php commander list:routes

# Clear views builds (compiler/builds) directory
php commander clear:builds
```

## create: commands

### Create model file
```bash
# It will create model file(s) in segments/Models directory
php commander create:model 'ModelName'
# Note : create:model will auto populate {$table} name with camel-case and pluralize pattern if no table given in command
php commander create:model 'Movie'
php commander create:model 'Entertainment/Movie'
# creates model file with attributes auto loaded
php commander create:model 'Entertainment/Movie' --table='movies' --primaryKey='id'
```

### Create controller file
```bash
# It will create controller file(s) in segments/Controllers directory
php commander create:controller 'MovieController'
php commander create:controller 'Entertainment/MovieController'
```

## Create view file
```bash
# It will create view file(s) in segments/views directory
php commander create:view 'welcome'
# to create view file in specific directory
php commander create:view 'movie/list.jly.php'
```

## Create barrier file
```bash
# It will create barrier files in segments/Barriers directory
php commander create:barrier 'VerifyToken'
```

## Create dbgram file (dbgram is to maintain database manipulations/migrations)
```bash
# It will create dbgram file(s) in locker/dbgrams directory
# create dbgram file to create movies table
# Here table and its operation will be auto adjusted with camel-case and pluralize format
php commander create:dbgram create_users_table
# Manually pass the table name and its operation like create | modify | drop | truncate
php commander create:dbgram create_users_table --table='movies' --action='create'
```

## Create dbfiller File (dbfiller file are to fill the data in the database)
```bash
# It will create dbfiller file(s) in locker/dbfillers directory
php commander create:dbfiller MovieFiller
# Create dbfiller file(s) in specific directory inside locker/dbfillers
php commander create:dbfiller entertainment/MovieFiller
```

###  Executing DBGrams (Database Versions/Migrations)

```bash
php commander run:dbgram
```

###  Rollback all adjusted dbgrams and execute DBGrams from fresh

```bash
php commander run:dbgram --refresh
```

### Execute DBGrams forcefully

```bash
php commander run:dbgram --force
```

### Drop all tables and execute DBGrams from again

```bash
php commander run:dbgram --refresh
```

### Setup `dbgrams` table in database to manage the dbgrams

```bash
php commander run:dbgram --setup
```

### Reset `dbgrams` table

```bash
php commander run:dbgram --reset
```

### Execute specific DBGrams files

```bash
php commander run:dbgram create_movies_table_11_02_2021_128976,add_column_to_movies_table_create_movies_table_12_02_2021_321234
```

### Rollback latest adjusted dbgram
```bash
php commander rollback:dbgram
```

### Rollback specific dbgrams with stack(group/batch) no
```bash
php commander rollback:dbgram --stack=2
```

### Rollback dbgrams with limit
```bash
# This will rollback last 3 stack dbgrams
php commander rollback:dbgram --limit=3
```

### Execute DB Fillers (locker/dbfillers/)
```bash
php commander run:dbfiller
```

### Execute specific DB Filler(s)
```bash
php commander run:dbfiller Entertainment/MovieFiller
```

### Refresh dbgrams with filling the data from filler files
```bash
php commander run:dbgram --refresh --fill
```

### Bones\DataWing for DBGram file(s)
```php
## Sample code

use Bones\DataWing;
use Bones\Skeletons\DataWing\Skeleton;

return new class 
{
	protected $table = 'posts';
	public function arise()
	{
		DataWing::create($this->table, function (Skeleton $table) {
			$table->id()->primaryKey();
			$table->string('title')->nullable(false);
			$table->text('description')->nullable();
			$table->unsignedBigInteger('posted_by');
			return $table;
		});
	}
	public function fall()
	{
		DataWing::drop($this->table);
	}
};

> arise() method will be called when dbgram is executed
> fall() method will be called when rollback is executed
```

### Bones\DataWing Methods
```php
# to create table
DataWing::create($this->table, function (Skeleton $table) {
    return $table;
});

# to modfiy table
DataWing::modify($this->table, function (Skeleton $table) {
    return $table;
});

# to truncate table
DataWing::truncate();

# to drop table
DataWing::drop();
```

### Bones\Skeletons\DataWing\Skeleton methods
```php
$table->engine(); // set table engine
$table->prefix(); // set table prefix
$table->collation(); // set table collation
$table->charSet(); // set table character set
$table->autoIncrement('column'); // Add auto increment integer column
$table->autoIncrementBig('column'); // Add auto increment big integer column
$table->integer('column');
$table->tinyInteger('column');
$table->smallInteger('column');
$table->mediumInteger('column');
$table->bigInteger('column');
$table->unsignedInteger('column');
$table->unsignedTinyInteger('column');
$table->unsignedSmallInteger('column');
$table->unsignedMediumInteger('column');
$table->unsignedBigInteger('column');
$table->float('column');
$table->floatAuto('column');
$table->real('column');
$table->serial('column');
$table->bit('column');
$table->double('column');
$table->decimal('column');
$table->unsignedFloat('column');
$table->unsignedFloatAuto('column');
$table->unsignedDouble('column');
$table->unsignedDecimal('column');
$table->char('column');
$table->varchar('column');
$table->string('column');
$table->text('column');
$table->mediumText('column');
$table->longText('column');
$table->boolean('column');
$table->enum('column', Array of Allowed Set);
$table->set('column', Array of Allowed Set);
$table->json('column');
$table->jsonb('column');
$table->date('column');
$table->dateTime('column');
$table->dateTimeTz('column');
$table->time('column');
$table->timeTz('column');
$table->year('column');
$table->geometry('column');
$table->point('column');
$table->linestring('column');
$table->polygon('column');
$table->geometryCollection('column');
$table->multiPoint('column');
$table->multiLineString('column');
$table->multiPolygon('column');
$table->multiPolygonZ('column');
$table->autoCalculateAsInt('column', 'expression');
$table->autoCalculateAsDouble('column', 'expression');
$table->autoCalculateAsFloat('column', 'expression');

$table->dropColumn('column'); // Drop column

$table->id(); // Add autoincrement big integer column as id
$table->id('tid'); // id() also accepts column name to add as autoincrement big integer
$table->timestamps(); // Adds created_at and updated_at columns in table with default values CURRENT_TIMESTAMP and CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP respectively
$table->rememberToken(); // Add column remember_token of string type

$table->setIndex(Array of columns); // Add index on columns
$table->setUnique(Array of columns); // Add unique index on columns
$table->setFullText(Array of columns); // Add fulltext index on columns
$table->setSpatialIndex(Array of columns); // Add spatial index on columns

$table->dropIndex(string index|Array of indexes); // Drop given index(s)
$table->dropForeign(string foregin_constraint|Array of foregin_constraints); // Drop foreign key constraint(s)

$table->renameTable('new_table_name'); // Rename table name
```

### Set additional attributes and operation on Skeleton column structure
```php
$table->id()->primaryKey(); // Set primary key to id column
$table->string('title')->nullable();
$table->string('title')->nullable(false)->default('Jolly!');
$table->boolean('email')->unique();
$table->bigInteger('followers')->unsigned();
$table->text('description')->comment('This explains the description');
$table->string('title')->after('id');
$table->string('title')->after('id')->modify(); // modify() used when need to alter existing column

$table->autoCalculateAsInt('virtual_column', 'buyprice * quantityinstock');
$table->autoCalculateAsInt('virtual_column', 'buyprice * quantityinstock')-virtual(); // 
$table->autoCalculateAsInt('virtual_column', 'buyprice * quantityinstock')->stored();

# Note : As per MySQL the virtual columns are calculated on the fly each time data is read whereas the stored column are calculated and stored physically when the data is updated.
```

### Database filler file
```php
namespace Bones\Skeletons\DBFiller;
use Bones\Database;

return new class
{
	protected $table = 'users';
	public function fill()
	{
		Database::insertMulti([
			[
				'name' => 'User ' . uniqid(),
				'email' => 'user' . generateOTP(8) . '@gmail.com',
				'is_active' => 1
			],
			[
				'name' => 'User ' . uniqid(),
				'email' => 'user' . generateOTP(8) . '@gmail.com',
				'is_active' => 1
			],
		], null, $this->table);
	}
};
```

### Stop the application
```bash
# This will load default template of 503 (settings/templates/). You can set your custom view in that file to load customized 503 page
php commander stop
```

### Stop the application with message
```bash
# This will load default template of 503 (settings/templates/) with message
php commander stop --message='App is down for certain time...'
```

### Start the application
```bash
# This will load default template of 503 (settings/ templates/) with message
php commander start
```

``Note : In case if start or stop command does not work then manually you can add `stop` file without extension and put (string) message inside the file to get message on your 503 template or you can simply leave it blank if no message needed.``
