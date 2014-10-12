<?php namespace TeenQuotes\Api\V1\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\URL;
use Quote;
use TeenQuotes\Api\V1\Interfaces\PaginatedContentInterface;
use TeenQuotes\Http\Facades\Response;
use User;

class SearchController extends APIGlobalController implements PaginatedContentInterface {
	
	public function getSearch($query)
	{
		$page = $this->getPage();
		$pagesize = $this->getPagesize();
				
		// Get content
		$quotes = App::make('TeenQuotes\Api\V1\Controllers\QuotesController')->getQuotesSearch($page, $pagesize, $query);
		$users = App::make('TeenQuotes\Api\V1\Controllers\UsersController')->getUsersSearch($page, $pagesize, $query);

		$totalQuotes = 0;
		$totalUsers = 0;

		if ( ! is_null($quotes) AND ! empty($quotes) AND $quotes->count() > 0) {
			$totalQuotes = Quote::whereRaw("MATCH(content) AGAINST(?)", array($query))
				// $query will NOT be bind here
				// it will be bind when calling setBindings
				->where('approved', '=', Quote::APPROVED)
				// WARNING 1 corresponds to approved = 1
				// We need to bind it again
				->setBindings([$query, Quote::APPROVED])
				->count();
		}

		if ( ! is_null($users) AND ! empty($users) AND $users->count() > 0)
			$totalUsers = User::partialLogin($query)
				->notHidden()
				->count();

		// Handle no results
		if ($totalQuotes == 0 AND $totalUsers == 0)
			return Response::json([
				'status' => 404,
				'error'  => 'No results have been found.'
			], 404);

		return Response::json([
			'quotes'       => $quotes->toArray(),
			'users'        => $users->toArray(),
			'total_quotes' => $totalQuotes,
			'total_users'  => $totalUsers,
			'pagesize'     => (int) $pagesize,
			'url'          => URL::current(),
		], 200, [], JSON_NUMERIC_CHECK);
	}

	public function getPagesize()
	{
		return Input::get('pagesize', Config::get('app.quotes.nbQuotesPerPage'));
	}
}