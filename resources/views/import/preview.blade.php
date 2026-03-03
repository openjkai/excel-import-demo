@extends('layouts.app')
@section('title', 'Preview Import')

@section('content')
<div class="space-y-6">
    {{-- Progress Steps --}}
    <nav class="flex items-center justify-center space-x-4 text-sm font-medium">
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">1</span> Upload</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">2</span> Map</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">3</span> Validate</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">4</span> Preview & Commit</span>
    </nav>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Total Rows</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $batch->total_rows }}</p>
        </div>
        <div class="bg-white rounded-xl border border-green-200 p-5 shadow-sm">
            <p class="text-sm text-green-600">Valid Rows</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $batch->valid_rows }}</p>
        </div>
        <div class="bg-white rounded-xl border border-red-200 p-5 shadow-sm">
            <p class="text-sm text-red-600">Errors</p>
            <p class="text-2xl font-bold text-red-700 mt-1">{{ $batch->error_rows }}</p>
        </div>
        <div class="bg-white rounded-xl border border-yellow-200 p-5 shadow-sm">
            <p class="text-sm text-yellow-600">Duplicates</p>
            <p class="text-2xl font-bold text-yellow-700 mt-1">{{ $batch->duplicate_rows }}</p>
        </div>
    </div>

    {{-- Overwrite Warning --}}
    @if($existingCount > 0)
    <div class="rounded-lg bg-amber-50 p-4 border border-amber-200">
        <div class="flex">
            <svg class="h-5 w-5 text-amber-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-amber-800">Overwrite Warning</h3>
                <p class="mt-1 text-sm text-amber-700">
                    <strong>{{ $existingCount }} existing records</strong> in the period
                    {{ $batch->period_start->format('M d, Y') }} — {{ $batch->period_end->format('M d, Y') }}
                    will be <strong>replaced</strong> when you commit.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Error Rows --}}
    @if($errorRows->count())
    <div class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden">
        <div class="px-6 py-4 bg-red-50 border-b border-red-200">
            <h3 class="text-sm font-semibold text-red-800">Rows with Errors ({{ $errorRows->count() }})</h3>
            <p class="text-xs text-red-600 mt-0.5">These rows will be skipped during commit.</p>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-red-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-red-600 uppercase">Row</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-red-600 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-red-600 uppercase">Errors</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-red-600 uppercase">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-red-100">
                    @foreach($errorRows as $row)
                    <tr class="bg-red-50/30">
                        <td class="px-4 py-2 font-mono text-red-700">{{ $row->row_number }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->status === 'duplicate' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($row->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-red-600 text-xs">
                            @foreach($row->errors ?? [] as $err)
                                <div>{{ $err }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-2 text-gray-600 text-xs font-mono">
                            {{ Str::limit(json_encode($row->mapped_data ?? $row->raw_data), 100) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Valid Rows Preview --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Valid Rows Preview (showing up to 100)</h3>
        </div>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                        @foreach($internalFields as $key => $config)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $config['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($validRows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-gray-400">{{ $row->row_number }}</td>
                        @foreach($internalFields as $key => $config)
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $row->mapped_data[$key] ?? '—' }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Commit Action --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Ready to Commit?</h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $batch->valid_rows }} valid rows will be inserted.
                    @if($existingCount > 0)
                        {{ $existingCount }} existing records will be overwritten.
                    @endif
                    This uses a Laravel queued job for safe processing.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('import.map', $batch) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Back to Mapping
                </a>
                <form action="{{ route('import.commit', $batch) }}" method="POST"
                    onsubmit="return confirm('This will overwrite existing records for the selected period. Continue?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Commit Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
