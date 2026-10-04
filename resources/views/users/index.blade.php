@extends('layouts.app')
@section('title', 'Users')
@section('eyebrow', 'People operations')
@section('content')
<section class="page-heading"><div><div class="crumb-context">Hospital operations <span>/</span> People operations</div><h1>Users & roles</h1><p>Provision access, update user information, and control account status.</p></div><span class="badge badge-danger">Admin only</span></section>
<section class="panel admin-action-bar"><div><h2>People operations</h2><p>Manage existing staff accounts from this page.</p></div><a class="button" href="{{ route('users.create') }}">＋ Provision account</a></section>
<section class="dashboard-grid">
    <article class="panel user-management-panel">
        <div class="panel-heading"><div><h2>People with access</h2><p>{{ $users->count() }} accounts in the system.</p></div></div>
        @if($users->isEmpty())
            <div class="empty-state"><strong>No users yet</strong>Create an account for a hospital team member.</div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>User</th><th>Contact</th><th>Department</th><th>Role</th><th>Status</th><th>Actions</th><th style="width: 18%;"></th></tr></thead><tbody>
            @foreach($users as $user)
                <tr><td><strong>{{ $user->name }}</strong></td><td>{{ $user->email }}<br>{{ $user->phone ?? '' }}</td><td>@if($user->department)<span class="badge badge-info">{{ $user->departmentLabel() }}</span>@else<span class="badge">Unassigned</span>@endif</td><td>@forelse($user->roles as $role)<span class="badge badge-info">{{ $role->name }}</span> @empty<span class="badge">Unassigned</span>@endforelse</td><td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}"><span class="status-dot status-dot-small"></span>{{ $user->is_active ? 'Active' : 'Disabled' }}</span></td><td><div class="table-actions"><a class="button button-secondary" href="{{ route('users.edit', $user) }}" title="Edit {{ $user->name }}">✎ <span>Edit</span></a>@if($user->id !== auth()->id())<form method="POST" action="{{ route('users.toggle-status', $user) }}" data-confirm="{{ $user->is_active ? 'Deactivate this user account?' : 'Activate this user account?' }}">@csrf<button class="button {{ $user->is_active ? 'button-danger' : 'button-success' }}" type="submit">{{ $user->is_active ? '− Deactivate' : '✓ Activate' }}</button></form>@else<span class="action-note">Current account</span>@endif</div></td><td></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </article>
</section>
@endsection