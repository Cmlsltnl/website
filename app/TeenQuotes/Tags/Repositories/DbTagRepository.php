<?php

/*
 * This file is part of the Teen Quotes website.
 *
 * (c) Antoine Augusti <antoine.augusti@teen-quotes.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TeenQuotes\Tags\Repositories;

use TeenQuotes\Quotes\Models\Quote;
use TeenQuotes\Tags\Models\Tag;

class DbTagRepository implements TagRepository
{
    /**
     * Create a new tag.
     *
     * @param string $name
     *
     * @return \TeenQuotes\Tags\Models\Tag
     */
    public function create($name)
    {
        return Tag::create(compact('name'));
    }

    /**
     * Get a tag thanks to its name.
     *
     * @param string $name
     *
     * @return \TeenQuotes\Tags\Models\Tag|null
     */
    public function getByName($name)
    {
        return Tag::whereName($name)->first();
    }

    /**
     * Add a tag to a quote.
     *
     * @param \TeenQuotes\Quotes\Models\Quote $q
     * @param \TeenQuotes\Tags\Models\Tag     $t
     */
    public function tagQuote(Quote $q, Tag $t)
    {
        $q->tags()->attach($t);
    }

    /**
     * Remove a tag from a quote.
     *
     * @param \TeenQuotes\Quotes\Models\Quote $q
     * @param \TeenQuotes\Tags\Models\Tag     $t
     */
    public function untagQuote(Quote $q, Tag $t)
    {
        $q->tags()->detach($t);
    }

    /**
     * Get a list of tags for a given quote.
     *
     * @param \TeenQuotes\Quotes\Models\Quote $q
     *
     * @return array
     */
    public function tagsForQuote(Quote $q)
    {
        return $q->tags()->lists('name');
    }

    /**
     * Get the total number of quotes having a tag.
     *
     * @param \TeenQuotes\Tags\Models\Tag $t
     *
     * @return int
     */
    public function totalQuotesForTag(Tag $t)
    {
        return $t->quotes()->count();
    }
}
