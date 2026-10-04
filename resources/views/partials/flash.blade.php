@if(session('success'))
    <div class="alert alert-success" data-dismissible><span>✓</span>{{ session('success') }}<button type="button" data-dismiss aria-label="Dismiss">×</button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger" data-dismissible><span>!</span>{{ session('error') }}<button type="button" data-dismiss aria-label="Dismiss">×</button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger" data-dismissible><span>!</span><div><strong>Please review the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div><button type="button" data-dismiss aria-label="Dismiss">×</button></div>
@endif
