@extends('layouts.app')
@section('title', 'Map Columns')

@section('content')
<div class="space-y-6">
    {{-- Progress Steps --}}
    <nav class="flex items-center justify-center space-x-4 text-sm font-medium">
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">1</span> Upload</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-brand-600"><span class="w-6 h-6 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs mr-1.5">2</span> Map</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-gray-400"><span class="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs mr-1.5">3</span> Validate</span>
        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
        <span class="flex items-center text-gray-400"><span class="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs mr-1.5">4</span> Preview & Commit</span>
    </nav>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Map Columns</h2>
            <p class="mt-1 text-sm text-gray-500">
                File: <span class="font-medium text-gray-700">{{ $batch->original_filename }}</span>
                — {{ $batch->total_rows }} rows detected
            </p>
        </div>

        <form action="{{ route('import.saveMapping', $batch) }}" method="POST">
            @csrf
            <div class="p-6 space-y-6">
                {{-- Period Selection --}}
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Import Period (data outside this range will not be affected)</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Start Date</label>
                            <input type="date" name="period_start" value="{{ old('period_start') }}"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm px-3 py-2 border" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">End Date</label>
                            <input type="date" name="period_end" value="{{ old('period_end') }}"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm px-3 py-2 border" required>
                        </div>
                    </div>
                </div>

                {{-- Column Mapping --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Column Mapping</h3>
                    <p class="text-xs text-gray-500 mb-4">Map each internal field to the corresponding spreadsheet column. Fields marked with * are required.</p>

                    <div class="space-y-3">
                        @foreach($internalFields as $fieldKey => $fieldConfig)
                        <div class="flex items-center gap-4 p-3 rounded-lg {{ $fieldConfig['required'] ? 'bg-blue-50 border border-blue-100' : 'bg-gray-50 border border-gray-100' }}">
                            <div class="w-1/3">
                                <span class="text-sm font-medium text-gray-800">
                                    {{ $fieldConfig['label'] }}
                                    @if($fieldConfig['required'])
                                        <span class="text-red-500">*</span>
                                    @endif
                                </span>
                                <span class="block text-xs text-gray-400">{{ $fieldConfig['type'] }}</span>
                            </div>

                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>

                            <div class="flex-1">
                                <select name="mapping[{{ $fieldKey }}]"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm px-3 py-2 border">
                                    <option value="">— Skip this field —</option>
                                    @foreach($headers as $header)
                                        <option value="{{ $header }}" {{ ($autoMapping[$fieldKey] ?? '') === $header ? 'selected' : '' }}>
                                            {{ $header }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Sample Preview --}}
                @if(count($headers))
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Detected Columns (first few values)</h3>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    @foreach($headers as $h)
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($batch->rows()->limit(3)->get() as $row)
                                <tr>
                                    @foreach($headers as $h)
                                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $row->raw_data[$h] ?? '' }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
                <a href="{{ route('import.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors">
                    Save Mapping & Validate
                    <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
