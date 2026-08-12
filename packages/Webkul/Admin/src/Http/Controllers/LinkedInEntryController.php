<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Webkul\Lead\Services\SourceAccessService;

class LinkedInEntryController extends Controller
{
    protected const STATUSES = [
        'pending'  => 'Pending',
        'accepted' => 'Accepted',
        'response' => 'Response',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);
        $filters = [
            'search'    => trim((string) $request->query('search', '')),
            'status'    => (string) $request->query('status', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
            'user_id'   => $isAdmin ? (string) $request->query('user_id', '') : '',
        ];

        if (! array_key_exists($filters['status'], self::STATUSES)) {
            $filters['status'] = '';
        }

        foreach (['date_from', 'date_to'] as $dateFilter) {
            if ($filters[$dateFilter] && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateFilter])) {
                $filters[$dateFilter] = '';
            }
        }

        if ($filters['user_id'] !== '' && (! ctype_digit($filters['user_id']) || (int) $filters['user_id'] <= 0)) {
            $filters['user_id'] = '';
        }

        if ($isAdmin && $filters['user_id'] !== '' && ! $this->userCanAccessLinkedInEntries((int) $filters['user_id'])) {
            $filters['user_id'] = '';
        }

        $query = DB::table('linkedin_entry')
            ->join('users', 'linkedin_entry.user_id', '=', 'users.id')
            ->select(
                'linkedin_entry.id',
                'linkedin_entry.user_id',
                'linkedin_entry.name',
                'linkedin_entry.url',
                'linkedin_entry.status',
                'linkedin_entry.created_at',
                'users.name as owner_name'
            )
            ->latest('linkedin_entry.id');

        if (! $isAdmin) {
            $query->where('linkedin_entry.user_id', $user->id);
        }

        if ($filters['search'] !== '') {
            $query->where(function ($query) use ($filters) {
                $query
                    ->where('linkedin_entry.name', 'like', "%{$filters['search']}%")
                    ->orWhere('linkedin_entry.url', 'like', "%{$filters['search']}%")
                    ->orWhere('linkedin_entry.status', 'like', "%{$filters['search']}%")
                    ->orWhere('users.name', 'like', "%{$filters['search']}%");
            });
        }

        if ($filters['status'] !== '') {
            $query->where('linkedin_entry.status', $filters['status']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('linkedin_entry.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('linkedin_entry.created_at', '<=', $filters['date_to']);
        }

        if ($isAdmin && $filters['user_id'] !== '') {
            $query->where('linkedin_entry.user_id', (int) $filters['user_id']);
        }

        $availableUsers = $isAdmin
            ? $this->linkedinEntryUsers()
            : collect([$user]);

        return view('admin::linkedin-entries.index', [
            'entries'  => $query->paginate(10)->withQueryString(),
            'statuses' => self::STATUSES,
            'availableUsers' => $availableUsers,
            'isAdmin'  => $isAdmin,
            'filters'  => $filters,
            'search'   => $filters['search'],
            'hasFilters' => $filters['status'] !== ''
                || $filters['date_from'] !== ''
                || $filters['date_to'] !== ''
                || $filters['user_id'] !== '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name'    => ['required', 'string', 'max:255'],
            'url'     => ['required', 'string', 'max:2048'],
        ]);

        $url = $this->normalizeUrl($data['url']);

        validator(['url' => $url], [
            'url' => ['required', 'url', 'max:2048'],
        ])->validate();

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        if ($isAdmin && empty($data['user_id'])) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['user_id' => 'Please select a user.']);
        }

        if ($isAdmin && ! $this->userCanAccessLinkedInEntries((int) $data['user_id'])) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['user_id' => 'Please select a user who has LinkedIn Entries access.']);
        }

        DB::table('linkedin_entry')->insert([
            'user_id'    => $isAdmin ? (int) $data['user_id'] : (int) $user->id,
            'name'       => $data['name'],
            'url'        => $url,
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session()->flash('success', 'LinkedIn entry created successfully.');

        return redirect()->route('admin.linkedin_entries.index');
    }

    public function importTemplate(): StreamedResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $headers = [
            'name*',
            'profile_url*',
        ];

        $samples = [
            ['Sarah Khan', 'https://www.linkedin.com/in/sarah-khan-marketing'],
            ['Ali Raza', 'https://www.linkedin.com/in/ali-raza-sales'],
        ];

        return response()->streamDownload(function () use ($headers, $samples) {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, $headers);

            foreach ($samples as $sample) {
                fputcsv($stream, $sample);
            }

            fclose($stream);
        }, 'linkedin-entry-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function acceptedImportTemplate(): StreamedResponse
    {
        $this->authorizeAccess('linkedin_entries.edit');

        $headers = [
            'profile_url*',
        ];

        $samples = [
            ['https://www.linkedin.com/in/sarah-khan-marketing'],
            ['https://www.linkedin.com/in/ali-raza-sales'],
        ];

        return response()->streamDownload(function () use ($headers, $samples) {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, $headers);

            foreach ($samples as $sample) {
                fputcsv($stream, $sample);
            }

            fclose($stream);
        }, 'linkedin-accepted-requests-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'file'           => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'import_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        if ($isAdmin && empty($data['import_user_id'])) {
            return redirect()
                ->back()
                ->withErrors(['import_user_id' => 'Please select an entry owner for this import.']);
        }

        if ($isAdmin && ! $this->userCanAccessLinkedInEntries((int) $data['import_user_id'])) {
            return redirect()
                ->back()
                ->withErrors(['import_user_id' => 'Please select a user who has LinkedIn Entries access.']);
        }

        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array) {}
            }, $data['file']);
        } catch (Throwable) {
            return $this->importResponse(0, [
                'The uploaded file could not be read. Please upload a valid CSV or XLSX file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return $this->importResponse(0, [
                'The import file has no data rows.',
            ], 422);
        }

        $headers = $this->normalizeImportHeaders(array_shift($rows));
        $missingHeaders = array_diff(['name', 'profile_url'], array_keys($headers));

        if (! empty($missingHeaders)) {
            return $this->importResponse(0, [
                'Missing required columns: '.implode(', ', array_map(fn ($column) => $column.'*', $missingHeaders)),
            ], 422);
        }

        $ownerId = $isAdmin ? (int) $data['import_user_id'] : (int) $user->id;
        $created = 0;
        $errors = [];
        $now = now();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $rowData = $this->mapImportRow($headers, $row);
            $rowErrors = $this->validateImportRow($rowData);

            if (! empty($rowErrors)) {
                $errors[] = 'Row '.$rowNumber.': '.implode(' ', $rowErrors);

                continue;
            }

            $url = $this->normalizeUrl((string) $rowData['profile_url']);
            $normalizedUrl = $this->normalizeProfileUrlForDuplicateCheck($url);

            if ($this->profileUrlExists($normalizedUrl)) {
                $errors[] = 'Row '.$rowNumber.': skipped duplicate profile URL.';

                continue;
            }

            DB::table('linkedin_entry')->insert([
                'user_id'    => $ownerId,
                'name'       => trim((string) $rowData['name']),
                'url'        => $url,
                'status'     => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $created++;
        }

        return $this->importResponse($created, $errors, $created || empty($errors) ? 200 : 422);
    }

    public function importStart(Request $request): JsonResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'file'           => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'import_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        if ($isAdmin && empty($data['import_user_id'])) {
            return response()->json([
                'message' => 'Please select an entry owner for this import.',
            ], 422);
        }

        if ($isAdmin && ! $this->userCanAccessLinkedInEntries((int) $data['import_user_id'])) {
            return response()->json([
                'message' => 'Please select a user who has LinkedIn Entries access.',
            ], 422);
        }

        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array) {}
            }, $data['file']);
        } catch (Throwable) {
            return response()->json([
                'message' => 'The uploaded file could not be read. Please upload a valid CSV or XLSX file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'The import file has no data rows.',
            ], 422);
        }

        $headers = $this->normalizeImportHeaders(array_shift($rows));
        $missingHeaders = array_diff(['name', 'profile_url'], array_keys($headers));

        if (! empty($missingHeaders)) {
            return response()->json([
                'message' => 'Missing required columns: '.implode(', ', array_map(fn ($column) => $column.'*', $missingHeaders)),
            ], 422);
        }

        $importRows = [];

        foreach ($rows as $index => $row) {
            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $importRows[] = [
                'row_number' => $index + 2,
                'data'       => $this->mapImportRow($headers, $row),
            ];
        }

        if (empty($importRows)) {
            return response()->json([
                'message' => 'The import file has no importable rows.',
            ], 422);
        }

        $token = (string) Str::uuid();
        $directory = storage_path('app/imports/pending');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->pendingImportPath($token), json_encode([
            'user_id'     => $isAdmin ? (int) $data['import_user_id'] : (int) $user->id,
            'rows'        => $importRows,
            'created'     => 0,
            'skipped'     => 0,
            'errors'      => [],
            'failed_rows' => [],
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'token'   => $token,
            'total'   => count($importRows),
            'message' => count($importRows).' row'.(count($importRows) === 1 ? '' : 's').' ready to import.',
        ]);
    }

    public function importProcess(Request $request): JsonResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'token'  => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $path = $this->pendingImportPath($data['token']);

        if (! is_file($path)) {
            return response()->json([
                'message' => 'Import session expired. Please upload the file again.',
            ], 404);
        }

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $userId = (int) ($payload['user_id'] ?? 0);

        if (! $userId) {
            @unlink($path);

            return response()->json([
                'message' => 'Import session is missing a valid entry owner. Please upload the file again.',
            ], 422);
        }

        $rows = $payload['rows'] ?? [];
        $total = count($rows);
        $offset = (int) $data['offset'];
        $chunkSize = 1;
        $chunk = array_slice($rows, $offset, $chunkSize);
        $now = now();

        if (! isset($payload['failed_rows']) || ! is_array($payload['failed_rows'])) {
            $payload['failed_rows'] = [];
        }

        foreach ($chunk as $row) {
            $rowData = $row['data'] ?? [];
            $rowErrors = $this->validateImportRow($rowData);

            if (! empty($rowErrors)) {
                $errorMessage = implode(' ', $rowErrors);
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$errorMessage;
                $payload['failed_rows'][] = [
                    'row_number' => $row['row_number'],
                    'data'       => $rowData,
                    'error'      => $errorMessage,
                ];

                continue;
            }

            $url = $this->normalizeUrl((string) $rowData['profile_url']);
            $normalizedUrl = $this->normalizeProfileUrlForDuplicateCheck($url);

            if ($this->profileUrlExists($normalizedUrl)) {
                $payload['skipped']++;
                $payload['errors'][] = 'Row '.$row['row_number'].': skipped duplicate profile URL.';

                continue;
            }

            DB::table('linkedin_entry')->insert([
                'user_id'    => $userId,
                'name'       => trim((string) $rowData['name']),
                'url'        => $url,
                'status'     => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $payload['created']++;
        }

        $processed = min($offset + count($chunk), $total);
        $done = $processed >= $total;

        if ($done) {
            @unlink($path);
        } else {
            file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $failedRows = $payload['failed_rows'] ?? [];

        return response()->json([
            'processed'   => $processed,
            'total'       => $total,
            'created'     => (int) ($payload['created'] ?? 0),
            'skipped'     => (int) ($payload['skipped'] ?? 0),
            'failed'      => count($failedRows),
            'failed_rows' => $done ? array_values($failedRows) : [],
            'errors'      => $payload['errors'] ?? [],
            'done'        => $done,
            'message'     => ((int) ($payload['created'] ?? 0)).' LinkedIn entr'.((int) ($payload['created'] ?? 0) === 1 ? 'y' : 'ies').' imported.',
        ]);
    }

    public function importRetry(Request $request): JsonResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'import_user_id'          => ['nullable', 'integer', 'exists:users,id'],
            'rows'                    => ['required', 'array', 'min:1'],
            'rows.*.row_number'       => ['required', 'integer', 'min:1'],
            'rows.*.data'             => ['required', 'array'],
            'rows.*.data.name'        => ['nullable', 'string', 'max:255'],
            'rows.*.data.profile_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        if ($isAdmin && empty($data['import_user_id'])) {
            return response()->json([
                'message' => 'Please select an entry owner for retry.',
            ], 422);
        }

        if ($isAdmin && ! $this->userCanAccessLinkedInEntries((int) $data['import_user_id'])) {
            return response()->json([
                'message' => 'Please select a user who has LinkedIn Entries access.',
            ], 422);
        }

        $userId = $isAdmin ? (int) $data['import_user_id'] : (int) $user->id;
        $created = 0;
        $skipped = 0;
        $errors = [];
        $failedRows = [];
        $now = now();

        foreach ($data['rows'] as $row) {
            $rowData = $row['data'] ?? [];
            $rowErrors = $this->validateImportRow($rowData);

            if (! empty($rowErrors)) {
                $errorMessage = implode(' ', $rowErrors);
                $errors[] = 'Row '.$row['row_number'].': '.$errorMessage;
                $failedRows[] = [
                    'row_number' => $row['row_number'],
                    'data'       => $rowData,
                    'error'      => $errorMessage,
                ];

                continue;
            }

            $url = $this->normalizeUrl((string) $rowData['profile_url']);
            $normalizedUrl = $this->normalizeProfileUrlForDuplicateCheck($url);

            if ($this->profileUrlExists($normalizedUrl)) {
                $skipped++;
                $errors[] = 'Row '.$row['row_number'].': skipped duplicate profile URL.';

                continue;
            }

            DB::table('linkedin_entry')->insert([
                'user_id'    => $userId,
                'name'       => trim((string) $rowData['name']),
                'url'        => $url,
                'status'     => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $created++;
        }

        return response()->json([
            'processed'   => count($data['rows']),
            'total'       => count($data['rows']),
            'created'     => $created,
            'skipped'     => $skipped,
            'failed'      => count($failedRows),
            'failed_rows' => $failedRows,
            'errors'      => $errors,
            'done'        => true,
            'message'     => $created.' LinkedIn entr'.($created === 1 ? 'y' : 'ies').' imported from retry.',
        ]);
    }

    public function acceptedImportStart(Request $request): JsonResponse
    {
        $this->authorizeAccess('linkedin_entries.edit');

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array) {}
            }, $data['file']);
        } catch (Throwable) {
            return response()->json([
                'message' => 'The uploaded file could not be read. Please upload a valid CSV or XLSX file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'The import file has no data rows.',
            ], 422);
        }

        $headers = $this->normalizeImportHeaders(array_shift($rows));
        $missingHeaders = array_diff(['profile_url'], array_keys($headers));

        if (! empty($missingHeaders)) {
            return response()->json([
                'message' => 'Missing required columns: '.implode(', ', array_map(fn ($column) => $column.'*', $missingHeaders)),
            ], 422);
        }

        $importRows = [];

        foreach ($rows as $index => $row) {
            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $rowData = $this->mapImportRow($headers, $row);

            $importRows[] = [
                'row_number'  => $index + 2,
                'profile_url' => $rowData['profile_url'] ?? null,
            ];
        }

        if (empty($importRows)) {
            return response()->json([
                'message' => 'The import file has no importable rows.',
            ], 422);
        }

        $token = (string) Str::uuid();
        $directory = storage_path('app/imports/pending');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->acceptedImportPath($token), json_encode([
            'rows'         => $importRows,
            'updated'      => 0,
            'missing'      => 0,
            'failed_rows'  => [],
            'missing_rows' => [],
            'errors'       => [],
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'token'   => $token,
            'total'   => count($importRows),
            'message' => count($importRows).' row'.(count($importRows) === 1 ? '' : 's').' ready to process.',
        ]);
    }

    public function acceptedImportProcess(Request $request): JsonResponse
    {
        $this->authorizeAccess('linkedin_entries.edit');

        $data = $request->validate([
            'token'  => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $path = $this->acceptedImportPath($data['token']);

        if (! is_file($path)) {
            return response()->json([
                'message' => 'Import session expired. Please upload the file again.',
            ], 404);
        }

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = $payload['rows'] ?? [];
        $total = count($rows);
        $offset = (int) $data['offset'];
        $chunkSize = 1;
        $chunk = array_slice($rows, $offset, $chunkSize);
        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        foreach ($chunk as $row) {
            $url = trim((string) ($row['profile_url'] ?? ''));

            if ($url === '') {
                $errorMessage = 'Profile URL is required.';
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$errorMessage;
                $payload['failed_rows'][] = [
                    'row_number'  => $row['row_number'],
                    'profile_url' => $url,
                    'error'       => $errorMessage,
                ];

                continue;
            }

            $url = $this->normalizeUrl($url);

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $errorMessage = 'Profile URL is invalid.';
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$errorMessage;
                $payload['failed_rows'][] = [
                    'row_number'  => $row['row_number'],
                    'profile_url' => $url,
                    'error'       => $errorMessage,
                ];

                continue;
            }

            $entry = $this->findLinkedInEntryByNormalizedUrl(
                $this->normalizeProfileUrlForDuplicateCheck($url),
                $isAdmin ? null : (int) $user->id
            );

            if (! $entry) {
                $payload['missing']++;
                $payload['errors'][] = 'Row '.$row['row_number'].': profile not sent request.';
                $payload['missing_rows'][] = [
                    'row_number'  => $row['row_number'],
                    'profile_url' => $url,
                    'error'       => 'Profile not sent request.',
                ];

                continue;
            }

            DB::table('linkedin_entry')
                ->where('id', $entry->id)
                ->update([
                    'status'     => 'accepted',
                    'updated_at' => now(),
                ]);

            $payload['updated']++;
        }

        $processed = min($offset + count($chunk), $total);
        $done = $processed >= $total;

        if ($done) {
            @unlink($path);
        } else {
            file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $failedRows = $payload['failed_rows'] ?? [];
        $missingRows = $payload['missing_rows'] ?? [];

        return response()->json([
            'processed'    => $processed,
            'total'        => $total,
            'updated'      => (int) ($payload['updated'] ?? 0),
            'missing'      => (int) ($payload['missing'] ?? 0),
            'failed'       => count($failedRows),
            'missing_rows' => $done ? array_values($missingRows) : [],
            'failed_rows'  => $done ? array_values($failedRows) : [],
            'errors'       => $payload['errors'] ?? [],
            'done'         => $done,
            'message'      => ((int) ($payload['updated'] ?? 0)).' LinkedIn entr'.((int) ($payload['updated'] ?? 0) === 1 ? 'y' : 'ies').' marked as accepted.',
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $this->authorizeAccess('linkedin_entries.edit');

        $data = $request->validate([
            'status' => ['required', 'in:pending,accepted,response'],
        ]);

        $entry = DB::table('linkedin_entry')->where('id', $id)->first();

        if (! $entry) {
            abort(404);
        }

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);

        if (! $isAdmin && (int) $entry->user_id !== (int) $user->id) {
            abort(403);
        }

        DB::table('linkedin_entry')
            ->where('id', $id)
            ->update([
                'status'     => $data['status'],
                'updated_at' => now(),
            ]);

        session()->flash('success', 'LinkedIn entry status updated.');

        return redirect()->back();
    }

    protected function authorizeAccess(?string $permission = 'linkedin_entries'): void
    {
        if (! bouncer()->hasPermission($permission)) {
            abort(401);
        }
    }

    protected function linkedinEntryUsers()
    {
        return DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.status', 1)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'roles.permission_type',
                'roles.permissions',
            ])
            ->filter(fn ($user) => $this->roleCanAccessLinkedInEntries($user->permission_type, $user->permissions))
            ->map(fn ($user) => (object) [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ])
            ->values();
    }

    protected function userCanAccessLinkedInEntries(int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $user = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $userId)
            ->where('users.status', 1)
            ->first([
                'roles.permission_type',
                'roles.permissions',
            ]);

        return $user
            && $this->roleCanAccessLinkedInEntries($user->permission_type, $user->permissions);
    }

    protected function roleCanAccessLinkedInEntries(?string $permissionType, mixed $permissions): bool
    {
        if ($permissionType === 'all') {
            return true;
        }

        if (is_string($permissions)) {
            $decodedPermissions = json_decode($permissions, true);
            $permissions = is_array($decodedPermissions) ? $decodedPermissions : [];
        }

        if (! is_array($permissions)) {
            return false;
        }

        return in_array('linkedin_entries', $permissions, true)
            || in_array('linkedin_entries.create', $permissions, true)
            || in_array('linkedin_entries.edit', $permissions, true);
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            return $url;
        }

        $host = preg_replace('/^www\./i', '', strtolower($parts['host']));
        $path = strtolower($parts['path'] ?? '');
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');

        return 'https://'.$host.$path;
    }

    protected function normalizeProfileUrlForDuplicateCheck(string $url): string
    {
        $normalized = strtolower(trim($url));
        $normalized = preg_replace('/^https?:\/\//', '', $normalized);
        $normalized = preg_replace('/^www\./', '', $normalized);

        return rtrim($normalized, '/');
    }

    protected function profileUrlExists(string $normalizedUrl): bool
    {
        return DB::table('linkedin_entry')
            ->whereRaw(
                "TRIM(TRAILING '/' FROM REPLACE(REPLACE(REPLACE(REPLACE(LOWER(url), 'https://www.', ''), 'http://www.', ''), 'https://', ''), 'http://', '')) = ?",
                [$normalizedUrl]
            )
            ->exists();
    }

    protected function findLinkedInEntryByNormalizedUrl(string $normalizedUrl, ?int $userId = null): ?object
    {
        return DB::table('linkedin_entry')
            ->whereRaw(
                "TRIM(TRAILING '/' FROM REPLACE(REPLACE(REPLACE(REPLACE(LOWER(url), 'https://www.', ''), 'http://www.', ''), 'https://', ''), 'http://', '')) = ?",
                [$normalizedUrl]
            )
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->first(['id', 'status']);
    }

    protected function normalizeImportHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $index => $header) {
            $normalized = strtolower(trim((string) $header));
            $normalized = rtrim($normalized, '*');
            $normalized = str_replace([' ', '-'], '_', $normalized);

            if ($normalized === 'url') {
                $normalized = 'profile_url';
            }

            if ($normalized !== '') {
                $headers[$normalized] = $index;
            }
        }

        return $headers;
    }

    protected function mapImportRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $column => $index) {
            $data[$column] = $this->mapImportCell($column, $index, $row);
        }

        return $data;
    }

    protected function mapImportCell(string $column, int $index, array $row): mixed
    {
        $value = $row[$index] ?? null;

        if ($column === 'profile_url'
            && is_string($value)
            && in_array(strtolower($value), ['http', 'https'], true)
            && isset($row[$index + 1])
            && is_string($row[$index + 1])
            && str_starts_with($row[$index + 1], '//')
        ) {
            return $value.':'.$row[$index + 1];
        }

        return $value;
    }

    protected function isEmptyImportRow(array $row): bool
    {
        return collect($row)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->isEmpty();
    }

    protected function validateImportRow(array $row): array
    {
        $errors = [];

        if (! filled($row['name'] ?? null)) {
            $errors[] = 'Name is required.';
        }

        if (! filled($row['profile_url'] ?? null)) {
            $errors[] = 'Profile URL is required.';
        } else {
            $url = $this->normalizeUrl((string) $row['profile_url']);

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = 'Profile URL is invalid.';
            }
        }

        return $errors;
    }

    protected function importResponse(int $created, array $errors = [], int $status = 200): RedirectResponse
    {
        $message = $created.' LinkedIn entr'.($created === 1 ? 'y' : 'ies').' imported.';

        if ($errors) {
            session()->flash($created ? 'warning' : 'error', $message.' '.count($errors).' row'.(count($errors) === 1 ? '' : 's').' failed. '.implode(' ', array_slice($errors, 0, 5)));
        } else {
            session()->flash('success', $message);
        }

        return redirect()->route('admin.linkedin_entries.index');
    }

    protected function pendingImportPath(string $token): string
    {
        $safeToken = preg_replace('/[^a-zA-Z0-9-]/', '', $token);

        return storage_path('app/imports/pending/linkedin-'.$safeToken.'.json');
    }

    protected function acceptedImportPath(string $token): string
    {
        $safeToken = preg_replace('/[^a-zA-Z0-9-]/', '', $token);

        return storage_path('app/imports/pending/linkedin-accepted-'.$safeToken.'.json');
    }
}
