@extends('layouts.app')
@section('title', 'Import Data')

@section('content')
<div class="space-y-8">
    {{-- Upload Section --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Upload Spreadsheet</h2>
                <p class="mt-1 text-sm text-gray-500">Upload an Excel (.xlsx, .xls) or CSV file to begin the import process.</p>
            </div>
            <a href="/sample_import.xlsx" download class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-brand-600 bg-brand-50 border border-brand-200 rounded-lg hover:bg-brand-100">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Download Sample .xlsx
            </a>
        </div>

        <form action="{{ route('import.upload') }}" method="POST" enctype="multipart/form-data" x-data="{ fileName: '', dragging: false }">
            @csrf
            <div class="p-6">
                <div
                    class="border-2 border-dashed rounded-xl p-10 text-center transition-colors"
                    :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-gray-300 hover:border-gray-400'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0]?.name"
                >
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <div class="mt-4">
                        <label class="cursor-pointer">
                            <span class="text-brand-600 font-medium hover:text-brand-500">Choose a file</span>
                            <span class="text-gray-500"> or drag and drop</span>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="sr-only" x-ref="fileInput"
                                @change="fileName = $event.target.files[0]?.name">
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Excel or CSV up to 20MB</p>
                    <p x-show="fileName" x-text="fileName" class="mt-3 text-sm font-medium text-brand-600" x-cloak></p>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors disabled:opacity-50"
                    :disabled="!fileName">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Upload & Parse
                </button>
            </div>
        </form>
    </div>

    {{-- Recent Imports --}}
    @if($batches->count())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Imports</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($batches as $batch)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $batch->original_filename }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'uploaded' => 'bg-blue-100 text-blue-800',
                                    'mapped' => 'bg-yellow-100 text-yellow-800',
                                    'validated' => 'bg-purple-100 text-purple-800',
                                    'committing' => 'bg-orange-100 text-orange-800',
                                    'committed' => 'bg-green-100 text-green-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$batch->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($batch->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $batch->total_rows }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($batch->period_start && $batch->period_end)
                                {{ $batch->period_start->format('M d, Y') }} — {{ $batch->period_end->format('M d, Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $batch->created_at->diffForHumans() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            @if($batch->status === 'uploaded')
                                <a href="{{ route('import.map', $batch) }}" class="text-brand-600 hover:text-brand-800 font-medium">Map Columns</a>
                            @elseif($batch->status === 'mapped')
                                <form action="{{ route('import.validate', $batch) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-brand-600 hover:text-brand-800 font-medium">Validate</button>
                                </form>
                            @elseif($batch->status === 'validated')
                                <a href="{{ route('import.preview', $batch) }}" class="text-brand-600 hover:text-brand-800 font-medium">Preview</a>
                            @elseif(in_array($batch->status, ['committing', 'committed', 'failed']))
                                <a href="{{ route('import.status', $batch) }}" class="text-brand-600 hover:text-brand-800 font-medium">View Status</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
