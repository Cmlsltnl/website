<?php namespace TeenQuotes\Models\Scopes;

use Illuminate\Support\Facades\Auth;
use NotAllowedException;
use User;

trait FavoriteQuoteTrait {
	
	/**
	 * Get the FavoriteQuote for the current user
	 * @throws \NotAllowedException when calling this when the visitor is not logged in
	 * @param  Illuminate\Database\Query\Builder $query
	 * @return Illuminate\Database\Query\Builder 
	 */
	public function scopeCurrentUser($query)
	{
		if (!Auth::check())
			throw new NotAllowedException("Can't get favorites quotes for a guest user!");

		return $query->where('user_id', '=', Auth::id());
	}

	/**
	 * Get FavoriteQuote for a given user
	 * @param Illuminate\Database\Query\Builder $query 
	 * @param  mixed $user int|User User's ID or User object
	 * @return Illuminate\Database\Query\Builder
	 */
	public function scopeForUser($query, $user)
	{
		if (is_numeric($user)) {
			$user_id = (int) $user;
			$user = User::where('id', '=', $user_id)->first();
		}

		return $query->where('user_id', '=', $user->id);
	}
}