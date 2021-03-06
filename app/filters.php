<?php

/*
 * This file is part of the Teen Quotes website.
 *
 * (c) Antoine Augusti <antoine.augusti@teen-quotes.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

App::before(function ($request) {
    if (Config::get('database.log', false) or App::environment('local')) {
        Event::listen('illuminate.query', function ($query, $bindings, $time, $name) {
            $data = compact('bindings', 'time', 'name');

            // Format binding data for sql insertion
            foreach ($bindings as $i => $binding) {
                if ($binding instanceof \DateTime) {
                    $bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                } elseif (is_string($binding)) {
                    $bindings[$i] = "'$binding'";
                }
            }

            // Insert bindings into query
            $query = str_replace(['%', '?'], ['%%', '%s'], $query);
            $query = vsprintf($query, $bindings);

            Log::info($query, $data);
        });
    }
});

App::after(function ($request, $response) {
    //
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function () {
    if (Auth::guest()) {
        if (Route::currentRouteName() == 'addquote') {
            // Flash an attribute in session to display a custom message
            // on the signin / signup page
            Session::flash('requireLoggedInAddQuote', true);

            return Redirect::guest(URL::route('signin'));
        }

        return Redirect::guest(URL::route('signin'))->with('warning', Lang::get('auth.requireLoggedIn'));
    }
});

Route::filter('admin', function () {
    if (!(Auth::check() and Auth::user()->is_admin)) {
        App::abort('401', 'Nothing to do here');
    }
});

Route::filter('auth.basic', function () {
    return Auth::basic();
});

Route::filter('session.remove', function () {
    return Config::set('session.driver', 'array');
});

Route::filter('search.isValid', function ($route) {
    // search.getResults has the query as a parameter
    // search.dispatcher uses a POST
    $query = (count($route->parameters()) > 0) ? $route->getParameter('query') : Input::get('search');

    $data = [
        'search' => $query,
    ];

    $validator = Validator::make($data, ['search' => 'min:3']);

    if ($validator->fails()) {
        return Redirect::route('search.form')->withErrors($validator)->withInput(['search' => $query]);
    }
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function () {
    if (Auth::check()) {
        return Redirect::route('home')->with('warning', Lang::get('auth.alreadyLoggedIn'));
    }
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function () {
    if (Session::token() != Input::get('_token')) {
        throw new Illuminate\Session\TokenMismatchException();
    }
});
