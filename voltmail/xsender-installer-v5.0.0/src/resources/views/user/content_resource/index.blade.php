@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ translate("Business Content Resources") }}</h3>
                <p class="text-muted mb-0">{{ translate("Maintain company knowledge, products, and approved facts for relevant warmup and outbound email content.") }}</p>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.content-resource.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line me-1"></i> {{ translate("Add Resource") }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ translate("All Business Knowledge Profiles") }}</h4>
                <span class="badge bg-light text-dark">{{ $resources->total() }} {{ translate("Total Profiles") }}</span>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Profile Name") }}</th>
                                <th scope="col">{{ translate("Company") }}</th>
                                <th scope="col">{{ translate("Industry / Audience") }}</th>
                                <th scope="col">{{ translate("Default") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col" class="text-end">{{ translate("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resources as $res)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="{{ route('user.content-resource.show', $res->id) }}" class="fw-semibold text-dark">
                                            {{ $res->name }}
                                        </a>
                                        <small class="text-muted">{{ $res->business_type ?: translate('General Business') }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ $res->company_name }}</span>
                                    @if($res->website)
                                        <br><small><a href="{{ $res->website }}" target="_blank" class="text-primary">{{ $res->website }}</a></small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark mb-1">{{ $res->industry ?: translate('N/A') }}</span>
                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($res->target_audience, 35) }}</small>
                                </td>
                                <td>
                                    @if($res->is_default)
                                        <span class="i-badge success-solid pill">{{ translate("Default") }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="i-badge {{ $res->status == 'active' ? 'success-soft' : 'warning-soft' }} pill">
                                        {{ ucfirst($res->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <a href="{{ route('user.content-resource.show', $res->id) }}" class="i-btn btn--info outline btn--sm" title="{{ translate('View & Preview Generation') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('user.content-resource.edit', $res->id) }}" class="i-btn btn--primary outline btn--sm" title="{{ translate('Edit') }}">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('user.content-resource.destroy', $res->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure you want to delete this resource?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="i-btn btn--danger outline btn--sm" title="{{ translate('Delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="ri-file-text-line fs-1 text-muted"></i>
                                        <h5 class="mt-2">{{ translate("No business content resources found") }}</h5>
                                        <p class="text-muted">{{ translate("Add your company profile, core offerings, and audience to enable realistic, personalized warmup content.") }}</p>
                                        <a href="{{ route('user.content-resource.create') }}" class="i-btn btn--primary btn--sm mt-2">
                                            <i class="ri-add-line me-1"></i> {{ translate("Create First Resource") }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($resources->hasPages())
                <div class="p-3">
                    {{ $resources->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</main>

@endsection
