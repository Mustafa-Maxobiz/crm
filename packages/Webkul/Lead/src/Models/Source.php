<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Source as SourceContract;

class Source extends Model implements SourceContract
{
    protected $table = 'lead_sources';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'parent_id',
        'sort_order',
    ];

    /**
     * Get the parent source.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get all parent sources (many-to-many).
     */
    public function parents()
    {
        return $this->belongsToMany(
            self::class,
            'lead_source_parents',
            'source_id',
            'parent_source_id'
        );
    }

    /**
     * Get the child sources (sub-sources).
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all child sources via many-to-many.
     */
    public function childSources()
    {
        return $this->belongsToMany(
            self::class,
            'lead_source_parents',
            'parent_source_id',
            'source_id'
        );
    }

    /**
     * Get only root sources (sources that are NOT sub-sources of any parent).
     * Excludes sources that appear in the pivot table as children.
     */
    public function scopeRoots($query)
    {
        return $query->whereNotExists(function ($subQuery) {
            $subQuery->select(\DB::raw(1))
                ->from('lead_source_parents')
                ->whereColumn('lead_source_parents.source_id', 'lead_sources.id');
        })->orderBy('sort_order');
    }

    /**
     * Get only sub-sources (sources with parent_id).
     */
    public function scopeSubSources($query)
    {
        return $query->whereNotNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass(), 'lead_source_id', 'id');
    }
}
