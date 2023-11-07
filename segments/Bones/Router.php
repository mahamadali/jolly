<?php

namespace Bones;

use Barriers\System\PreventCSRFToken;
use Bones\Str;
use Bones\Request;
use Bones\Session;
use Bones\Commander;
use Closure;
use Bones\RouteException;
use Bones\BadMethodException;

class Router
{
    protected static $routes;
    protected static $current;
    protected static $currentRouteMethod;
    protected static $parent;
    protected static $parentPrefixSet;
    protected static $parentSettingSet;
    private static $_instance = null;
    protected static $dropback = null;

    public static function getInstance()
    {
        if (self::$_instance === null)
            self::$_instance = new self;
        return self::$_instance;
    }

    public static function __callStatic(string $method, array $arguments)
    {
        if (in_array(strtoupper(strtolower($method)), ['ANY', 'GET', 'PUT', 'POST', 'DELETE', 'PATCH', 'UPDATE', 'OPTIONS'])) {
            return self::init($arguments[0], strtolower($method), $arguments[1]);
        }

        throw new BadMethodException('Request Method [' . strtoupper(strtolower($method)) . '] not supported for route ' . $arguments[0], 404);
    }

    public static function init($route, string $method, $callback)
    {
        $parentSettings = [];
        if (!empty(self::$parent)) {
            $parent = self::bunchAttrs(self::$parent);
            $parentPrefix = (!empty($parent[0])) ? $parent[0] : '';
            if (gettype($route) === 'string') {
                $route = $parentPrefix . $route;
            } else if (gettype($route) === 'array') {
                foreach ($route as $routeIndex => $subRoute) {
                    $route[$routeIndex] = $parentPrefix . $subRoute;
                }
            }
            $parentSettings = (!empty($parent[1])) ? $parent[1] : [];
        }
        if (gettype($route) === 'string') {
            $route = trim($route, '/');
            self::register($route, $method, $callback, $parentSettings);
        } else if (gettype($route) === 'array') {
            foreach ($route as $subRoute) {
                $subRoute = trim($subRoute, '/');
                self::register($subRoute, $method, $callback, $parentSettings);
            }
        }
        self::$current = $route;
        self::$currentRouteMethod = strtoupper($method);
        return self::getInstance();
    }

    public static function bunch(string $prefix, array $settings = [], $callback = null)
    {
        if (empty(self::$parent)) self::$parent = [];
        self::$parent[] = [$prefix, $settings];
        call_user_func($callback);
        array_pop(self::$parent);
    }

    public static function modules($modules = [])
    {
        foreach ($modules as $prefix => $action) {
            $singular_prefix = (isset($action['entity-alias']) && !empty($action['entity-alias'])) ? $action['entity-alias'] : Str::singular(Str::toSlug($prefix));
            $barriers = (isset($action['barriers']) && !empty($action['barriers'])) ? $action['barriers'] : [];
            $segments = (isset($action['segments']) && !empty($action['segments'])) ? $action['segments'] : [];

            self::bunch($prefix, ['as' => ltrim($prefix, '/') . '.', 'barrier' => [$barriers]], function () use ($action, $singular_prefix, $segments) {
                self::get('/', [$action['controller'], 'index'])->name('index');
                self::get('/' . (!empty($segments['create']) ? $segments['create'] : 'create') . '', [$action['controller'], 'create'])->name('create');
                self::post('/', [$action['controller'], 'store'])->name('store');
                self::get('/{' . $singular_prefix . '}', [$action['controller'], 'show'])->name('show');
                self::get('/{' . $singular_prefix . '}/' . (!empty($segments['edit']) ? $segments['edit'] : 'edit') . '', [$action['controller'], 'edit'])->name('edit');
                self::patch('/{' . $singular_prefix . '}', [$action['controller'], 'update'])->name('update');
                self::delete('/{' . $singular_prefix . '}', [$action['controller'], 'destroy'])->name('destroy');
            });
        }
    }

    public static function bunchAttrs($parentAttrs)
    {
        $prefixSet = '';
        $settingSet = [];

        foreach ($parentAttrs as $parentAttr) {
            if (!empty($parentAttr[0]) && gettype($parentAttr[0]) === 'string')
                $prefixSet .= $parentAttr[0];

            if (!empty($parentAttr[1]) && gettype($parentAttr[1]) === 'array') {
                if (!empty($parentAttr[1]['barrier']) && gettype($parentAttr[1]['barrier']) === 'array') {
                    if (!empty($settingSet['barrier'])) {
                        array_push($settingSet['barrier'], $parentAttr[1]['barrier']);
                    } else {
                        $settingSet['barrier'] = $parentAttr[1]['barrier'];
                    }
                }

                if (!empty($parentAttr[1]['as']) && gettype($parentAttr[1]['as']) === 'string') {
                    if (!empty($settingSet['as'])) {
                        $settingSet['as'] .= $parentAttr[1]['as'];
                    } else {
                        $settingSet['as'] = $parentAttr[1]['as'];
                    }
                }
            }
        }

        return [$prefixSet, $settingSet];
    }

    public static function register($route, string $method, $callback, array $parentSettings = [])
    {
        $route = trim($route, '/');
        if (empty($method)) $method = 'ANY';
        $method = strtoupper($method);
        self::$routes[$method][$route]['caption'] = $route;
        self::$routes[$method][$route]['method'] = strtolower($method);
        self::$routes[$method][$route]['callback'] = $callback;
        if (!empty($parentSettings)) {
            if (!empty($parentSettings['barrier'])) {
                self::barrier($parentSettings['barrier'], $route, $method);
            }
            if (!empty($parentSettings['as'])) {
                self::name($parentSettings['as'], $route, true, $method);
            }
            if (!empty($parentSettings['response'])) {
                self::response($parentSettings['response'], $route, $method);
            }
        }
    }

    public static function dispatch($route)
    {
        if (!self::__validateGlobalChecks()) {
            return false;
        }

        $routeSegments = explode('?', $route);
        $route = trim($routeSegments[0], '/');
        Session::appendSetReserved('latest_routes', $route);
        self::clearSavedRoutes();

        $method = self::requestMethod();
        self::validateRequestMethod($method);
        $method = self::$currentRouteMethod;

        if (empty(self::$routes[$method][$route])) {
            if (!empty($matchedRoute = self::checkRoutePatternMatch($route, $method))) {
                if (empty($matchedRoute['route']))
                    self::setError(404);

                $callback = self::$routes[$matchedRoute['method']][$matchedRoute['route']]['callback'];
                if (!self::validateRequest(self::$routes[$matchedRoute['method']][$matchedRoute['route']])) {
                    self::setError(401);
                }

                if (!empty($matchedRoute['optionalParams'])) {
                    $callbackParams = array_values($matchedRoute['optionalParams']);
                    if (gettype($callback) == 'object') {
                        $response = call_user_func_array($callback, array_merge([new Request($_REQUEST, $_FILES, self::$routes[$matchedRoute['method']][$matchedRoute['route']])], $callbackParams));
                        self::serve($response, self::$routes[$matchedRoute['method']][$matchedRoute['route']]);
                    } else if (gettype($callback) == 'array') {
                        $invokableCallbackParams = $callback;
                        $classToInvoke = $invokableCallbackParams[0];
                        $methodToInvoke = $invokableCallbackParams[1];
                        if (!class_exists($classToInvoke))
                            throw new RouteException($classToInvoke . ' not found in route file', 404);
                        if (!method_exists($classToInvoke, $methodToInvoke))
                            throw new RouteException($methodToInvoke . ' not found in ' . $classToInvoke, 404);
                        $response = self::verifyBarriers(self::$routes[$matchedRoute['method']][$matchedRoute['route']], [
                            $classToInvoke,
                            $methodToInvoke,
                            $callbackParams
                        ]);
                        self::serve($response, self::$routes[$matchedRoute['method']][$matchedRoute['route']]);
                    } else {
                        op($callback);
                    }
                }
            } else {
                self::setError(404);
            }
        } else {
            $callback = self::$routes[$method][$route]['callback'];
            if (!self::validateRequest(self::$routes[$method][$route])) {
                self::setError(401);
            }
            if ($callback instanceof Closure) {
                $response = call_user_func($callback, new Request($_REQUEST, $_FILES, self::$routes[$method][$route]));
                self::serve($response, self::$routes[$method][$route]);
            } else if (gettype($callback) == 'array') {
                $callbackParams = $callback;
                $classToInvoke = $callbackParams[0];
                $methodToInvoke = $callbackParams[1];
                if (!class_exists($classToInvoke))
                    throw new RouteException($classToInvoke . ' not found in route file', 404);
                if (!method_exists($classToInvoke, $methodToInvoke))
                    throw new RouteException($methodToInvoke . ' not found in ' . $classToInvoke, 404);
                $response = self::verifyBarriers(self::$routes[$method][$route], [
                    $classToInvoke,
                    $methodToInvoke,
                    []
                ]);
                self::serve($response, self::$routes[$method][$route]);
            } else {
                op($callback);
            }
        }
    }

    public static function requestMatchedRoutes($method = null)
    {
        $matchedRoutes = [];

        if (isset(self::$routes)) {
            foreach (self::$routes as $routeMethod => $routeInfo) {
                if (!empty($method) && strtolower($routeMethod) != 'any' && strtolower($method) != strtolower($routeMethod))
                    continue;

                foreach ($routeInfo as $route) {
                    if (request()->matchesTo(self::toPattern($route['caption'])))
                        $matchedRoutes[$routeMethod . '://' . $route['caption']] = $route;
                }
            }
        }

        return $matchedRoutes;
    }

    public static function validateRequestMethod($method)
    {
        $matchedRoutes = self::requestMatchedRoutes();

        if (empty($matchedRoutes)) self::setError(404);

        $is_authenticated = false;
        $has_matched_route = false;
        $current_uri = ltrim(URL::removeQuery(request()->currentUri()), '/');
        foreach ($matchedRoutes as $matchedRoute) {

            $matched_route_segments = explode('/', $matchedRoute['caption']);
            $current_page_segments = explode('/', ltrim(URL::removeQuery(request()->currentUri()), '/'));

            if (count($matched_route_segments) === count($current_page_segments) && Url::matchesTo($current_uri, self::toPattern($matchedRoute['caption']))) {
                $has_matched_route = true;
                if (!$is_authenticated && strtolower($matchedRoute['method']) == 'any') {
                    self::$currentRouteMethod = 'ANY';
                    $is_authenticated = true;
                }

                if (!$is_authenticated && strtolower($matchedRoute['method']) == strtolower($method)) {
                    self::$currentRouteMethod = strtoupper($matchedRoute['method']);
                    $is_authenticated = true;
                }
            }
        }

        if ($has_matched_route && !$is_authenticated) self::setError(401);
    }

    public static function validateRequest(array $route = [])
    {
        if ($route['method'] == 'any') return true;
        $isValid = true;
        if (!empty($route['method'])) {
            $method = strtoupper($route['method']);
            if ($method !== strtoupper($_SERVER['REQUEST_METHOD'])) {
                $isValid = false;
            }

            if (!in_array($method, ['GET', 'POST'])) {
                if (!empty($_REQUEST['_method']) && $method == strtoupper($_REQUEST['_method'])) {
                    $isValid = true;
                }
            }

            if (!in_array($method, ['GET'])) {
                $preventCSRFTokenBarrierClass = PreventCSRFToken::class;
                if (class_exists($preventCSRFTokenBarrierClass)) {
                    $preventCSRFTokenBarrier = new $preventCSRFTokenBarrierClass();

                    // Skip defined $excludeRoutes from csrf-token check
                    $skipCSRFCheck = false;
                    if (isset($preventCSRFTokenBarrier->excludeRoutes) && !empty($excludeRoutes = $preventCSRFTokenBarrier->excludeRoutes)) {
                        foreach ($excludeRoutes as $excludedRoute) {
                            if (!$skipCSRFCheck && request()->matchesTo($excludedRoute)) {
                                $skipCSRFCheck = true;
                            }
                        }
                    }

                    if (!$skipCSRFCheck && !$preventCSRFTokenBarrier->check(request())) {
                        throw new RouteException('Unauthenticated: request denied by ' . $preventCSRFTokenBarrierClass . ' check', 402);
                    }
                } else {
                    throw new RouteException('Unauthenticated: Barriers\System\PreventCSRFToken must exist with check method to prevent CSRF attack', 402);
                }
            }
        }

        return $isValid;
    }

    public static function verifyBarriers(array $route = [], array $closureParams = [])
    {
        if (!empty($route)) {
            if (!empty($route['barriers'])) {
                foreach ($route['barriers'] as $barrier) {
                    if (empty($barrier['name'])) {
                        throw new RouteException('Empty barrier found for route ' . $route['caption']);
                    }

                    if (class_exists($barrier['name'])) {
                        $barrierClass = $barrier['name'];
                    } else {
                        if ((Str::startsWith($barrier['name'], 'Barrier') && Str::endsWith($barrier['name'], 'Barrier')))
                            $barrierClass = $barrier['name'];
                        else if (Str::startsWith($barrier['name'], 'Barrier'))
                            $barrierClass = $barrier['name'];
                        else
                            $barrierClass = '\\Barriers\\' . $barrier['name'];
                    }

                    if (!class_exists($barrierClass))
                        $barrierClass = self::findBarrierByName($barrier['name']);

                    if (class_exists($barrierClass)) {
                        if (!method_exists($barrierClass, 'check')) {
                            throw new BadMethodException('Method not found: check() method must present in ' . $barrierClass, 404);
                        }

                        $barrierObj = new $barrierClass();

                        // Skip defined $excludeRoutes from csrf-token check
                        $skipBarrierCheck = false;
                        if (isset($barrierObj->excludeRoutes) && !empty($excludeRoutes = $barrierObj->excludeRoutes)) {
                            foreach ($excludeRoutes as $excludedRoute) {
                                if (!$skipBarrierCheck && request()->matchesTo($excludedRoute)) {
                                    $skipBarrierCheck = true;
                                }
                            }
                        }

                        if (!$skipBarrierCheck && !$barrierObj->check(request())) {
                            if (method_exists($barrierObj, 'throwback'))
                                return $barrierObj->throwback();
                            else
                                throw new RouteException('Unauthenticated: request denied by ' . $barrierClass . ' check', 402);
                        }
                    } else {
                        throw new RouteException(((!empty($barrierClass)) ? $barrierClass : $barrier['name']) . ' barrier not found');
                    }
                }
            }
        }
        return call_user_func_array([(new $closureParams[0]()), $closureParams[1]], array_merge([(new Request($_REQUEST, $_FILES, $route))], $closureParams[2]));
    }

    public static function findBarrierByName($barrier)
    {
        if (class_exists($barrier)) {
            return $barrier;
        }

        $aliases = Str::array_change_key_case_recursive(setting('aliases', []), CASE_LOWER);

        if (empty($aliases) || empty($aliases['barriers'])) {
            return null;
        }

        return $aliases['barriers'][$barrier];
    }

    public static function checkRoutePatternMatch($pageRoute, $method)
    {
        if (empty(self::$routes)) {
            throw new RouteException('No routes defined for the application');
        }

        $pageRouteSegments = explode('/', $pageRoute);
        $pageRouteSegments = array_map(function ($pageRouteSegment) {
            return urldecode($pageRouteSegment);
        }, $pageRouteSegments);

        $routeNames = [];
        $requestMatchedRoutes = self::requestMatchedRoutes($method);
        foreach ($requestMatchedRoutes as $key => $routeInfo) {
            $routeNames[$key] = $routeInfo['caption'];
        }

        $matchedRoute = null;
        $optionalParams = [];
        foreach ($routeNames as $routeMethod => $route) {
            $routeMethodSegments = explode('://', $routeMethod);
            $routeMethod = (!empty($routeMethodSegments[0])) ? $routeMethodSegments[0] : 'GET';
            if (empty($matchedRoute) && Str::contains($route, '{') && Str::contains($route, '}')) {
                $routeSegments = explode('/', $route);
                $routeSegmentsPresence = [];
                $requiredSegmentsCount = 0;
                $optionalSegmentsCount = 0;
                foreach ($routeSegments as $segmentIndex => $segment) {
                    $routeSegmentsPresence[$segment] = self::getRouteSegmentPresenseAttrs($segment);
                    if ($routeSegmentsPresence[$segment]['type'] == 'required') {
                        $requiredSegmentsCount++;
                    } else {
                        $optionalSegmentsCount++;
                    }
                }

                self::$routes[$routeMethod][$route]['syntax'] = $routeSegmentsPresence;

                // self::debugRoutePatterns([self::$routes[$method][$route], $pageRouteSegments, $routeSegmentsPresence, $routeSegments], true);

                if (count($pageRouteSegments) >= $requiredSegmentsCount && count($pageRouteSegments) <= ($requiredSegmentsCount + $optionalSegmentsCount)) {
                    $rsAttendanceIndex = 0;
                    $isMatched = true;
                    foreach ($routeSegmentsPresence as $rsAttendanceName => $rsAttendance) {
                        if ((string) $rsAttendance['mode'] == 'static' && (string) $rsAttendance['type'] == 'required' && (empty($pageRouteSegments[$rsAttendanceIndex]) || $pageRouteSegments[$rsAttendanceIndex] != $rsAttendanceName)) {
                            $isMatched = false;
                            break;
                        }

                        if ((string) $rsAttendance['mode'] != 'static') {
                            $byPassWhereChecks = false;
                            if ((string) $rsAttendance['type'] == 'optional' && empty($pageRouteSegments[$rsAttendanceIndex])) {
                                $byPassWhereChecks = true;
                            }
                            if (!$byPassWhereChecks && !self::verifySegmentWhereChecks($route, $routeMethod, $rsAttendanceName, (!empty($pageRouteSegments[$rsAttendanceIndex])) ? $pageRouteSegments[$rsAttendanceIndex] : '')) {
                                throw new RouteException('Status 402: route ' . $route . ' does not match regex required for ' . $rsAttendanceName . ' segment');
                            }
                            $optionalParams[$rsAttendanceName] = (!empty($pageRouteSegments[$rsAttendanceIndex])) ? $pageRouteSegments[$rsAttendanceIndex] : '';
                        }

                        $rsAttendanceIndex++;
                    }
                    if ($isMatched) {
                        $matchedRoute = $route;
                        $matchedRouteInfo = self::$routes[$routeMethod][$matchedRoute];
                        $method = $routeMethod;

                        $callback = $matchedRouteInfo['callback'];
                        if ($callback instanceof Closure || $callback instanceof String) {
                            $closureInfo = new \ReflectionFunction($callback);
                            $optionalParams = self::bindParamsImplicitly($matchedRoute, $routeMethod, $closureInfo, $optionalParams);
                        } else if (is_array($callback)) {
                            $classToInvoke = $callback[0];
                            $methodToInvoke = $callback[1];
                            $classReflectionInfo = new \ReflectionMethod($classToInvoke, $methodToInvoke);
                            $optionalParams = self::bindParamsImplicitly($matchedRoute, $routeMethod, $classReflectionInfo, $optionalParams);
                        }
                    }
                }
            }
        }

        return [
            'route' => $matchedRoute,
            'method' => $method,
            'optionalParams' => $optionalParams
        ];
    }

    public static function bindParamsImplicitly(string $matchedRoute, $method, $closure, $optionalParams = [])
    {
        $optionalParamsClone = array_values($optionalParams);
        $paramCount = 0;
        foreach ($closure->getParameters() as $param) {
            if ($paramCount >= count($optionalParamsClone)) {
                throw new RouteException('Invalid Route Syntax: `' . self::$routes[$method][$matchedRoute]['caption'] . '`. Dynamic parameters count [' . count($optionalParamsClone) . '] must be same with associated closure. Exactly {' . count($optionalParamsClone) . '} required and {' . (count($closure->getParameters()) - count($optionalParamsClone)) . '} found in ' . (string) $closure);
            }
            if ($param->hasType()) {
                $paramClass = (phpversion() >= 8) ? $param->getType() : $param->getClass()->name;
                if (!Str::contains($paramClass, 'Bones\Request')) {
                    $bindModelImplicitly = (string) $paramClass;
                    $modelObj = (new $bindModelImplicitly);
                    $columnToBind = (property_exists($modelObj, 'route_bind_column')) ? self::accessProtected($modelObj, 'route_bind_column') : (self::accessProtected($modelObj, 'primary_key'));
                    $columnValueToCompare = $optionalParamsClone[$paramCount];
                    $optionalParamsClone[$paramCount] = $modelObj->where($columnToBind, $columnValueToCompare)->first();
                    if (empty($optionalParamsClone[$paramCount])) {
                        return error(404);
                    }
                    $paramCount++;
                }
            }
        }

        if (!empty($optionalParamsClone) && (count($optionalParams) == count($optionalParamsClone))) {
            $optionalParamCount = 0;
            foreach ($optionalParams as &$optionalParam) {
                $optionalParam = $optionalParamsClone[$optionalParamCount];
                $optionalParamCount++;
            }
        }

        return $optionalParams;
    }

    public static function accessProtected($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    public static function getRouteSegmentPresenseAttrs($segment)
    {
        if (empty($segment)) {
            return [
                'mode' => 'static',
                'type' => 'required'
            ];
        }

        if (Str::startsWith($segment, '{') && Str::endsWith($segment, '}') && !Str::startsWith($segment, '{?')) {
            return [
                'mode' => 'dynamic',
                'type' => 'required'
            ];
        } else if (Str::startsWith($segment, '{?') && Str::endsWith($segment, '}')) {
            return [
                'mode' => 'dynamic',
                'type' => 'optional'
            ];
        } else {
            return [
                'mode' => 'static',
                'type' => 'required'
            ];
        }
    }

    public static function verifySegmentWhereChecks(string $route, string $method, string $segmentKey, string $segmentValue)
    {
        if (empty(self::$routes[$method][$route]['routeParamChecks'])) return true;
        $whereChecks = self::$routes[$method][$route]['routeParamChecks'];
        $segmentKey = Str::multiReplace($segmentKey, ['{?', '{', '}'], ['', '', '']);
        $hasPassed = true;
        foreach ($whereChecks as $where) {
            if ($where['name'] == $segmentKey) {
                $where['regex'] = '/' . $where['regex'] . '/';
                try {
                    if (!preg_match($where['regex'], $segmentValue)) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    throw new RouteException('Invalid regex found for route ' . $route . ' for segment ' . $segmentKey);
                }
            }
        }
        return $hasPassed;
    }

    public static function name(string $name, $route = null, bool $nameFromParent = false, $method = null)
    {
        $route = (!empty($route)) ? $route : self::$current;
        $method = (!empty($method)) ? $method : self::$currentRouteMethod;

        if (is_array($route)) {
            foreach ($route as $route) {
                $route = trim($route, '/');
                self::$routes[$method][$route]['namedAs'] = (!empty(self::$routes[$method][$route]['namedAs'])) ? self::$routes[$method][$route]['namedAs'] . $name : $name;
                self::$routes[$method][$route]['nameFromParent'] = $nameFromParent;
            }
        } else {
            self::$routes[$method][$route]['namedAs'] = (!empty(self::$routes[$method][$route]['namedAs'])) ? self::$routes[$method][$route]['namedAs'] . $name : $name;
            self::$routes[$method][$route]['nameFromParent'] = $nameFromParent;
        }
        return self::getInstance();
    }

    public static function where(string $param, string $regex = '')
    {
        if (is_array(self::$current)) {
            foreach (self::$current as $route) {
                $route = trim($route, '/');
                self::$routes[self::$currentRouteMethod][$route]['routeParamChecks'][] = ['name' => $param, 'regex' => $regex];
            }
        } else {
            self::$routes[self::$currentRouteMethod][self::$current]['routeParamChecks'][] = ['name' => $param, 'regex' => $regex];
        }
        return self::getInstance();
    }

    public static function barrier($barriers, $route = null, $method = null)
    {
        $route = (!empty($route)) ? $route : self::$current;
        $method = (!empty($method)) ? $method : self::$currentRouteMethod;

        if (is_array($route)) {
            foreach ($route as $route) {
                $route = trim($route, '/');
                if (gettype($barriers) == 'string') {
                    $barriers = explode(',', $barriers);
                }
                self::setBarriers($route, $method, $barriers);
            }
        } else {
            if (gettype($barriers) == 'string') {
                $barriers = explode(',', $barriers);
            }
            self::setBarriers($route, $method, $barriers);
        }
        return self::getInstance();
    }

    public static function withoutBarrier($barriers, $route = null, $method = null)
    {
        $route = (!empty($route)) ? $route : self::$current;
        $method = (!empty($method)) ? $method : self::$currentRouteMethod;

        if (is_array($route)) {
            foreach ($route as $route) {
                $route = trim($route, '/');
                if (gettype($barriers) == 'string') {
                    $barriers = explode(',', $barriers);
                }
                self::removeBarriers($route, $method, $barriers);
            }
        } else {
            if (gettype($barriers) == 'string') {
                $barriers = explode(',', $barriers);
            }
            self::removeBarriers($route, $method, $barriers);
        }
        return self::getInstance();
    }

    public static function setBarriers(string $route, string $method, array $barriers = [])
    {
        if (!is_array($barriers)) return self::$routes[$method][$route];
        foreach ($barriers as $barrier) {
            if (is_array($barrier)) {
                self::setBarriers($route, $method, $barrier);
            } else {
                $barrier = trim($barrier);
                if (!empty($barrierAlias = self::findBarrierByName($barrier))) {
                    $barrier = $barrierAlias;
                }
                self::$routes[$method][$route]['barriers'][] = ['name' => $barrier];
            }
        }
        return self::$routes[$method][$route];
    }

    public static function removeBarriers(string $route, string $method, array $barriers = [])
    {
        if (!is_array($barriers)) return self::$routes[$method][$route];

        if (empty(self::$routes[$method][$route]['barriers'])) return self::$routes[$method][$route];

        foreach ($barriers as $barrierIndex => $barrier) {
            $barrier = trim($barrier);
            if (!empty($barrierAlias = self::findBarrierByName($barrier))) {
                $barriers[$barrierIndex] = $barrierAlias;
            }
        }

        foreach (self::$routes[$method][$route]['barriers'] as $barrierIndex => $barrier) {
            foreach ($barrier as $barrierName) {
                if (in_array($barrierName, $barriers)) {
                    unset(self::$routes[$method][$route]['barriers'][$barrierIndex]);
                }
            }
        }
        return self::$routes[$method][$route];
    }

    public static function response(string $response, $route = null, $method = null)
    {
        $route = (!empty($route)) ? $route : self::$current;
        $method = (!empty($method)) ? $method : self::$currentRouteMethod;

        self::setResponse($route, $method, $response);
        return self::getInstance();
    }

    public static function setResponse(string $route, string $method, string $response = '')
    {
        self::$routes[$method][$route]['response'] = $response;
        return self::$routes[$method][$route];
    }

    public static function serveAs($route = null)
    {
        if (!empty($route) && !empty($route['response']))
            return $route['response'];
        return '';
    }

    public static function serve($content, $route = null)
    {
        response()->format(self::serveAs($route));

        // Calculate request->response total execution time in seconds
        $current_time = microtime(true);
        $execution_start_time = (float) session()->get('execution_start_time');

        if (is_string($content)) {
            echo $content;
        } else {
            if (is_array($content)) {
                if (empty($response) || $response == 'application/json') {
                    response()->format(self::serveAs($route));
                    echo json_encode($content);
                }
            } else if (is_object($content)) {
                echo json_encode($content);
            }
        }
    }

    /** Debug route patterns logs 
     * 
     * @param array $logsOf
     * @param bool $stopExecution to print and die
     * 
     * return print $logsOf
     */
    public static function debugRoutePatterns(array $logsOf = [], $stopExecution = false)
    {
        if ($stopExecution)
            opd($logsOf);
        else
            op($logsOf);
    }

    public static function setError($error_code = 404, $error = '')
    {
        if (in_array($error_code, [400, 401, 403, 404, 405, 429, 500, 501, 502, 503])) {

            if ($error_code == 404 && self::$dropback != null) {
                call_user_func(self::$dropback);
                exit;
            }

            echo error($error_code, compact('error'));
            exit;
        }

        throw new RouteException('Error code ' . $error_code . ' returned');
    }

    public static function clearSavedRoutes()
    {
        $cachedRoutes = Session::getReserved('latest_routes');
        if (!empty($cachedRoutes) && is_array($cachedRoutes)) {
            krsort($cachedRoutes);
            $cachedRoutes = array_unique($cachedRoutes);
            $latest_routes = [];
            $cachedRoutesCount = 1;
            foreach ($cachedRoutes as $cachedRoute) {
                if ($cachedRoutesCount > 2)
                    break;
                $latest_routes[]  = $cachedRoute;
                $cachedRoutesCount++;
            }
            Session::setReserved('latest_routes', $latest_routes);
        }
        return true;
    }

    public static function requestMethod()
    {
        if (!empty($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['GET']))
            return $_SERVER['REQUEST_METHOD'];

        if (!empty($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['POST']) && !empty($_REQUEST['_method']))
            return $_REQUEST['_method'];

        return (!empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'ANY');
    }

    public static function prevRoute()
    {
        $cachedRoutes = Session::getReserved('latest_routes');
        return (!empty($cachedRoutes) && is_array($cachedRoutes) && count($cachedRoutes) > 1) ? url($cachedRoutes[1]) : url('/');
    }

    public static function currentRoute()
    {
        $cachedRoutes = Session::getReserved('latest_routes');
        return (!empty($cachedRoutes) && is_array($cachedRoutes) && count($cachedRoutes) >= 1) ? url($cachedRoutes[0]) : url('/');
    }

    public static function list()
    {
        return self::$routes;
    }

    public static function toPattern($route)
    {
        $routeSegments = explode('/', $route);
        foreach ($routeSegments as &$routeSegment) {
            if (Str::startsWith($routeSegment, '{') && Str::endsWith($routeSegment, '}'))
                $routeSegment = '*';
        }

        return implode('/', $routeSegments);
    }

    public static function url(string $path)
    {
        $url = setting('app.base_url') . '/';

        if (!empty($subDir = setting('app.sub_dir', ''))) {
            $url .= $subDir . '/';
        }

        return $url . $path;
    }

    public static function exists(string $routeToCheck, $return = false)
    {
        $isExists = false;
        foreach (self::$routes as $routeInfo) {
            foreach ($routeInfo as $routePattern => $route) {
                if (isset($route['nameFromParent']) && !$route['nameFromParent'] && !empty($route['namedAs']) && $route['namedAs'] == $routeToCheck) {
                    if ($return) {
                        return [
                            'info' => $route,
                            'pattern' => $routePattern
                        ];
                    }
                    $isExists = true;
                    break;
                }
            }
        }
        return $isExists;
    }

    public static function find(string $route)
    {
        return self::exists($route, true);
    }

    public static function prepare(string $route, array $segmentValues = [])
    {
        if (self::exists($route)) {
            $routeInfo = self::find($route);
            if (!empty($routeInfo['pattern'])) {
                $routeSyntax = explode('/', $routeInfo['pattern']);
                $finalRouteBlocks = [];
                $segmentHasSyntaxParam = false;

                foreach ($routeSyntax as $syntaxParam) {
                    if (Str::startsWith($syntaxParam, '{') && Str::endsWith($syntaxParam, '}')) {
                        $syntaxParam = Str::removeCharAt($syntaxParam, 0);
                        $syntaxParam = Str::removeCharAt($syntaxParam, strlen($syntaxParam) - 1);
                    } else if (Str::startsWith($syntaxParam, '{?') && Str::endsWith($syntaxParam, '}')) {
                        $syntaxParam = Str::removeCharAt($syntaxParam, 0);
                        $syntaxParam = Str::removeCharAt($syntaxParam, 1);
                        $syntaxParam = Str::removeCharAt($syntaxParam, strlen($syntaxParam) - 1);
                    }
                    if (!$segmentHasSyntaxParam && array_key_exists($syntaxParam, $segmentValues))
                        $segmentHasSyntaxParam = true;
                }

                if ((count($segmentValues) == count($segmentValues, COUNT_RECURSIVE)) && !$segmentHasSyntaxParam) {
                    $dynamicSegmentCount = 0;
                    foreach ($routeSyntax as $segmentCount => $segment) {
                        if (Str::startsWith($segment, '{') && Str::endsWith($segment, '}')) {
                            if (isset($segmentValues[$dynamicSegmentCount])) {
                                $finalRouteBlocks[$segment] = $segmentValues[$dynamicSegmentCount];
                                $dynamicSegmentCount++;
                            }
                        } else {
                            $finalRouteBlocks[$segment] = $segment;
                        }
                    }
                } else {
                    foreach ($routeSyntax as $segment) {
                        if (Str::startsWith($segment, '{') && !Str::startsWith($segment, '{?') && Str::endsWith($segment, '}')) {
                            $segmentMustPresent = Str::removeWords($segment, ['{', '}']);
                            if (!array_key_exists($segmentMustPresent, $segmentValues)) {
                                throw new RouteException('{' . $segmentMustPresent . '} must present in route ' . $routeInfo['pattern']);
                            } else {
                                $finalRouteBlocks[$segment] = $segmentValues[$segmentMustPresent];
                            }
                        } else if (Str::startsWith($segment, '{?') && Str::endsWith($segment, '}')) {
                            $segmentOptional = Str::removeWords($segment, ['{?', '}']);
                            if (array_key_exists($segmentOptional, $segmentValues)) {
                                $finalRouteBlocks[$segment] = $segmentValues[$segmentOptional];
                            }
                        } else {
                            $finalRouteBlocks[$segment] = $segment;
                        }
                    }
                }

                $queryParams = array_diff($segmentValues, $finalRouteBlocks);

                return URL::addQueryParams(url(implode('/', $finalRouteBlocks)), $queryParams);
            } else {
                return url($routeInfo['pattern']);
            }
        } else {
            throw new RouteException('404: Route ' . $route . ' not found');
        }
    }

    public static function dropback($dropback = null)
    {
        self::$dropback = $dropback;
    }

    public static function __validateGlobalChecks()
    {
        $proceedToNext = true;
        if (file_exists('locker/system/stop')) {
            render(setting('templates.503', 'defaults/503'), [
                'message' => file_get_contents((new Commander())->appStopperFile)
            ]);
            return false;
        }

        return $proceedToNext;
    }
}