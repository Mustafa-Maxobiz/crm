<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\SourceDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\SourceRepository;

class SourceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected SourceRepository $sourceRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(SourceDataGrid::class)->process();
        }

        return view('admin::settings.sources.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse
    {
        $this->validate(request(), [
            'name'        => ['required', 'unique:lead_sources,name'],
            'sub_sources' => ['nullable', 'string'],
        ]);

        Event::dispatch('settings.source.create.before');

        $source = $this->sourceRepository->create(request()->only(['name', 'sort_order']));

        // Create sub-sources if provided
        if (request('sub_sources')) {
            \Log::info('Sub-sources received:', ['sub_sources' => request('sub_sources')]);
            
            $subSourceNames = array_filter(array_map('trim', explode(',', request('sub_sources'))));
            
            \Log::info('Parsed sub-source names:', ['names' => $subSourceNames]);
            
            foreach ($subSourceNames as $subSourceName) {
                // Check if sub-source already exists
                $existingSubSource = $this->sourceRepository->findOneWhere(['name' => $subSourceName]);
                
                if ($existingSubSource) {
                    // Link existing sub-source to this parent
                    \Log::info('Linking existing sub-source:', ['id' => $existingSubSource->id, 'name' => $subSourceName]);
                    $existingSubSource->parents()->attach($source->id);
                } else {
                    // Create new sub-source and link to this parent
                    \Log::info('Creating new sub-source:', ['name' => $subSourceName]);
                    $subSource = $this->sourceRepository->create([
                        'name' => $subSourceName,
                        'sort_order' => 100,
                    ]);
                    $subSource->parents()->attach($source->id);
                }
            }
        } else {
            \Log::info('No sub-sources provided');
        }

        Event::dispatch('settings.source.create.after', $source);

        return new JsonResponse([
            'data'    => $source,
            'message' => trans('admin::app.settings.sources.index.create-success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $source = $this->sourceRepository->findOrFail($id);
        
        // Load child sources (sub-sources linked to this parent)
        $source->load('childSources');
        
        // Get sub-source names as comma-separated string
        $source->sub_sources = $source->childSources->pluck('name')->implode(', ');

        return new JsonResponse([
            'data' => $source,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): JsonResponse
    {
        $this->validate(request(), [
            'name'        => 'required|unique:lead_sources,name,'.$id,
            'sub_sources' => ['nullable', 'string'],
        ]);

        Event::dispatch('settings.source.update.before', $id);

        $source = $this->sourceRepository->update(request()->only(['name', 'sort_order']), $id);

        \Log::info('Updating source:', ['id' => $id, 'sub_sources' => request('sub_sources')]);

        // Sync sub-sources
        if (request()->has('sub_sources')) {
            $subSourceNames = request('sub_sources') 
                ? array_filter(array_map('trim', explode(',', request('sub_sources'))))
                : [];
            
            \Log::info('Parsed sub-source names for update:', ['names' => $subSourceNames]);
            
            $subSourceIds = [];
            
            foreach ($subSourceNames as $subSourceName) {
                // Check if sub-source already exists
                $existingSubSource = $this->sourceRepository->findOneWhere(['name' => $subSourceName]);
                
                if ($existingSubSource) {
                    $subSourceIds[] = $existingSubSource->id;
                } else {
                    // Create new sub-source
                    $subSource = $this->sourceRepository->create([
                        'name' => $subSourceName,
                        'sort_order' => 100,
                    ]);
                    $subSourceIds[] = $subSource->id;
                }
            }
            
            \Log::info('Syncing sub-source IDs:', ['ids' => $subSourceIds]);
            
            // Sync the relationship (this will remove any not in the list)
            $source->childSources()->sync($subSourceIds);
        }

        Event::dispatch('settings.source.update.after', $source);

        return new JsonResponse([
            'data'    => $source,
            'message' => trans('admin::app.settings.sources.index.update-success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $source = $this->sourceRepository->findOrFail($id);

        if ($source->leads()->count() > 0) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.sources.index.delete-failed-associated-leads'),
            ], 400);
        }

        try {
            Event::dispatch('settings.source.delete.before', $id);

            $source->delete();

            Event::dispatch('settings.source.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.sources.index.delete-success'),
            ], 200);
        } catch (Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.sources.index.delete-failed'),
            ], 400);
        }
    }
    
    /**
     * Get sub-sources for a given source.
     */
    public function getSubSources(int $id): JsonResponse
    {
        $source = $this->sourceRepository->findOrFail($id);
        
        // Load child sources (sub-sources)
        $source->load('childSources');
        
        return new JsonResponse([
            'sub_sources' => $source->childSources->map(function ($subSource) {
                return [
                    'id' => $subSource->id,
                    'name' => $subSource->name,
                ];
            }),
        ]);
    }
}
