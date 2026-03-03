@extends('layouts.app')
@section('title', 'Import Status')

@section('content')
<div class="max-w-2xl mx-auto" x-data="importStatus()" x-init="startPolling()">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-8 text-center">
            {{-- Status Icon --}}
            <template x-if="status === 'committing'">
                <div>
                    <div class="mx-auto w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Processing Import...</h2>
                    <p class="mt-2 text-sm text-gray-500">The import is being committed via a background queue job. This may take a moment.</p>
                </div>
            </template>

            <template x-if="status === 'committed'">
                <div>
                    <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Import Committed Successfully!</h2>
                    <p class="mt-2 text-sm text-gray-500">
                        <span x-text="validRows"></span> records have been inserted into the database.
                    </p>
                    <p class="mt-1 text-xs text-gray-400" x-text="'Committed at: ' + committedAt"></p>
                </div>
            </template>

            <template x-if="status === 'failed'">
                <div>
                    <div class="mx-auto w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Import Failed</h2>
                    <p class="mt-2 text-sm text-gray-500">Something went wrong during the commit. The transaction was rolled back — no data was affected.</p>
                </div>
            </template>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <div class="grid grid-cols-3 gap-4 text-center text-sm">
                <div>
                    <p class="text-gray-500">File</p>
                    <p class="font-medium text-gray-900">{{ $batch->original_filename }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Period</p>
                    <p class="font-medium text-gray-900">
                        {{ $batch->period_start?->format('M d, Y') }} — {{ $batch->period_end?->format('M d, Y') }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-500">Valid Rows</p>
                    <p class="font-medium text-gray-900">{{ $batch->valid_rows }}</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-center space-x-4">
            <a href="{{ route('import.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Back to Imports
            </a>
            <a href="{{ route('records') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">
                View Records
            </a>
        </div>
    </div>
</div>

<script>
function importStatus() {
    return {
        status: '{{ $batch->status }}',
        validRows: {{ $batch->valid_rows }},
        committedAt: '{{ $batch->committed_at?->toDateTimeString() ?? "" }}',
        polling: null,
        startPolling() {
            if (this.status === 'committed' || this.status === 'failed') return;
            this.polling = setInterval(() => this.checkStatus(), 2000);
        },
        async checkStatus() {
            try {
                const res = await fetch('{{ route("import.status.json", $batch) }}');
                const data = await res.json();
                this.status = data.status;
                this.validRows = data.valid_rows;
                this.committedAt = data.committed_at || '';
                if (data.status === 'committed' || data.status === 'failed') {
                    clearInterval(this.polling);
                }
            } catch (e) {
                console.error('Poll error', e);
            }
        }
    }
}
</script>
@endsection
