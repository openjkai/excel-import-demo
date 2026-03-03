@extends('layouts.app')
@section('title', 'Financial Records')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Financial Records</h2>
                <p class="mt-1 text-sm text-gray-500">Browse all committed records in the database.</p>
            </div>
            <form method="GET" action="{{ route('records') }}" class="flex items-center space-x-3">
                <input type="date" name="from" value="{{ request('from') }}" class="text-sm rounded-lg border-gray-300 border px-3 py-1.5" placeholder="From">
                <input type="date" name="to" value="{{ request('to') }}" class="text-sm rounded-lg border-gray-300 border px-3 py-1.5" placeholder="To">
                <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700">Filter</button>
                @if(request('from') || request('to'))
                    <a href="{{ route('records') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($records as $record)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-900 whitespace-nowrap">{{ $record->transaction_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-gray-700 font-mono">{{ $record->account_code }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ Str::limit($record->description, 50) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 font-mono">{{ $record->debit > 0 ? number_format($record->debit, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 font-mono">{{ $record->credit > 0 ? number_format($record->credit, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $record->reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $record->department ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            No records found. Import data to see records here.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $records->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
