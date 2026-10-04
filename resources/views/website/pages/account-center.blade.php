<!-- resources/views/website/pages/account-center.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Account Center</h2>
                    <p class="mt--10 mb-0">
                        Manage your security, emails, and privacy settings
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                {{-- Breadcrumb Navigation --}}
                <nav aria-label="Account sections" class="mb-4">
                    <div class="nav nav-pills nav-justified flex-wrap gap-2" role="tablist">
                        <a class="nav-link active" href="#security" role="tab" data-bs-toggle="pill">
                            <i class="feather-shield me-1"></i> Security
                        </a>
                        <a class="nav-link" href="#emails" role="tab" data-bs-toggle="pill">
                            <i class="feather-mail me-1"></i> Email Addresses
                        </a>
                        <a class="nav-link" href="#sessions" role="tab" data-bs-toggle="pill">
                            <i class="feather-monitor me-1"></i> Active Sessions
                        </a>
                        <a class="nav-link" href="#2fa" role="tab" data-bs-toggle="pill">
                            <i class="feather-lock me-1"></i> Two-Factor
                        </a>
                        <a class="nav-link text-danger" href="#danger" role="tab" data-bs-toggle="pill">
                            <i class="feather-trash-2 me-1"></i> Danger Zone
                        </a>
                    </div>
                </nav>

                <div class="tab-content" id="accountTabsContent">

                    {{-- ========== SECURITY TAB ========== --}}
                    <div class="tab-pane fade show active" id="security" role="tabpanel">
                        <div class="rbt-service rbt-service-2 radius-10 mb--40">
                            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                                <h4 class="title mb-0">Password</h4>
                            </div>

                            @if (session('status') === 'password-changed')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Your password has been updated successfully.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('account.password.update') }}" class="needs-validation row g-3" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="col-12">
                                    <label for="current_password" class="form-label fw-semibold">Current Password</label>
                                    <input type="password"
                                           class="form-control form-control-lg @error('current_password') is-invalid @enderror"
                                           id="current_password"
                                           name="current_password"
                                           required
                                           autocomplete="current-password">
                                    @error('current_password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="password" class="form-label fw-semibold">New Password</label>
                                    <input type="password"
                                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                                           id="password"
                                           name="password"
                                           required
                                           autocomplete="new-password">
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Must be at least 8 characters.</div>
                                </div>

                                <div class="col-12">
                                    <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password</label>
                                    <input type="password"
                                           class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                                           id="password_confirmation"
                                           name="password_confirmation"
                                           required
                                           autocomplete="new-password">
                                    @error('password_confirmation')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <hr class="my-4">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-lg btn-primary">Update Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        {{-- Security Recommendations --}}
                        <div class="rbt-service rbt-service-2 radius-10 bg-light">
                            <h5 class="title mb-3"><i class="feather-alert-triangle me-2 text-warning"></i> Security Recommendations</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 {{ session('status') === 'password-changed' ? 'border-success' : 'border-secondary' }}">
                                        <h6 class="fw-semibold"><i class="feather-lock me-2"></i> Strong Password</h6>
                                        <p class="small text-muted mb-0">Use a unique password with 12+ characters, mixed case, numbers, and symbols.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 border-warning">
                                        <h6 class="fw-semibold"><i class="feather-shield me-2"></i> Enable 2FA</h6>
                                        <p class="small text-muted mb-0">Add two-factor authentication for an extra layer of security.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 border-info">
                                        <h6 class="fw-semibold"><i class="feather-mail me-2"></i> Verified Email</h6>
                                        <p class="small text-muted mb-0">Keep your primary email verified for account recovery.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 border-secondary">
                                        <h6 class="fw-semibold"><i class="feather-monitor me-2"></i> Review Sessions</h6>
                                        <p class="small text-muted mb-0">Regularly check active sessions and revoke suspicious ones.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ========== EMAILS TAB ========== --}}
                    <div class="tab-pane fade" id="emails" role="tabpanel">
                        <div class="rbt-service rbt-service-2 radius-10 mb--40">
                            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                                <h4 class="title mb-0">Email Addresses</h4>
                                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                                    <i class="feather-plus me-2"></i> Add Email
                                </button>
                            </div>

                            @if (session('status') === 'email-added')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Email added! A verification link has been sent to the new address.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('status') === 'verification-sent')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Verification email sent. Please check your inbox.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('status') === 'email-verified')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Email verified successfully! You can now set it as your primary email.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('status') === 'primary-changed')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Primary email updated. This is now your main login and notification address.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('status') === 'email-removed')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Email address removed.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('status') === 'already-primary')
                                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-info me-2"></i>
                                    This email is already your primary address.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Email Address</th>
                                            <th>Status</th>
                                            <th>Primary</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($emails as $email)
                                            <tr>
                                                <td>
                                                    <strong>{{ $email->email }}</strong>
                                                    @if ($email->is_primary)
                                                        <span class="badge bg-primary ms-2">Primary</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($email->is_verified)
                                                        <span class="badge bg-success">
                                                            <i class="feather-check-circle me-1"></i> Verified
                                                        </span>
                                                    @else
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="feather-alert-circle me-1"></i> Unverified
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($email->is_primary)
                                                        <span class="text-success fw-semibold">
                                                            <i class="feather-star me-1"></i> Yes
                                                        </span>
                                                    @else
                                                        <span class="text-muted">No</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        @if (! $email->is_verified)
                                                            <form action="{{ route('account.emails.resend', $email) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-outline-secondary" title="Resend verification">
                                                                    <i class="feather-send"></i>
                                                                </button>
                                                            </form>
                                                        @endif

                                                        @if ($email->is_verified && ! $email->is_primary)
                                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#setPrimaryModal{{ $email->id }}" title="Set as primary">
                                                                <i class="feather-star"></i>
                                                            </button>
                                                        @endif

                                                        @if (! $email->is_primary)
                                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeEmailModal{{ $email->id }}" title="Remove">
                                                                <i class="feather-trash-2"></i>
                                                            </button>
                                                        @else
                                                            <span class="text-muted" title="Primary email cannot be removed">
                                                                <i class="feather-lock"></i>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Security Notice --}}
                        <div class="rbt-service rbt-service-2 radius-10 bg-light">
                            <h5 class="title mb-3"><i class="feather-shield me-2"></i> Security Notes</h5>
                            <ul class="mb-0 small text-muted">
                                <li>Your <strong>primary email</strong> is used for login, password resets, and important notifications.</li>
                                <li>Adding a new email requires your <strong>current password</strong>.</li>
                                <li>Setting an email as primary requires it to be <strong>verified</strong> and your <strong>current password</strong>.</li>
                                <li>Removing an email requires your <strong>current password</strong>. You cannot remove the primary email.</li>
                                <li>All email changes send a <strong>notification to your old primary email</strong> for security.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ========== SESSIONS TAB ========== --}}
                    <div class="tab-pane fade" id="sessions" role="tabpanel">
                        <div class="rbt-service rbt-service-2 radius-10 mb--40">
                            <h4 class="title mb-3">Active Sessions</h4>
                            <p class="text-muted mb-4">Review your active sessions. Revoke any sessions you don't recognize.</p>

                            @if (session('status') === 'session-revoked')
                                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                    <i class="feather-check-circle me-2"></i>
                                    Session has been revoked successfully.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if ($activeSessions->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Device</th>
                                                <th>Browser / OS</th>
                                                <th>IP Address</th>
                                                <th>Status</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($activeSessions as $login)
                                                <tr>
                                                    <td>{{ $login->login_at->format('M j, Y g:i A') }}</td>
                                                    <td>{{ $login->device_type }}</td>
                                                    <td>{{ $login->browser }} / {{ $login->operating_system }}</td>
                                                    <td>{{ $login->ip_address }}</td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            Active
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        @if ($login->id !== ($activeSessions->first()?->id))
                                                            <form action="{{ route('account.sessions.revoke', $login) }}" method="POST" class="d-inline-flex align-items-center gap-2" onsubmit="return confirm('Are you sure you want to revoke this session?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Current password" required style="width: 150px; height: 34px; padding: 0.375rem 0.75rem;">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke this session" style="height: 34px; padding: 0.375rem 0.75rem;">
                                                                    <i class="feather-x me-1"></i> Revoke
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-muted small">Current session</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted mb-0">No login history recorded yet.</p>
                            @endif
                        </div>

                        <div class="rbt-service rbt-service-2 radius-10 bg-light">
                            <h5 class="title mb-3"><i class="feather-info me-2"></i> Session Security</h5>
                            <ul class="mb-0 small text-muted">
                                <li>Sessions are automatically revoked when you change your password.</li>
                                <li>Enable 2FA to require a code for new logins.</li>
                                <li>If you see an unfamiliar session, change your password immediately.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ========== 2FA TAB ========== --}}
                    <div class="tab-pane fade" id="2fa" role="tabpanel">
                        @if (auth()->user()->hasTwoFactorEnabled())
                            {{-- 2FA Enabled View --}}
                            <div class="rbt-service rbt-service-2 radius-10 mb--40">
                                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                                    <h4 class="title mb-0"><i class="feather-lock text-success me-2"></i> Two-Factor Authentication</h4>
                                    <span class="badge bg-success fs-6 px-3 py-2">Enabled</span>
                                </div>

                                <div class="alert alert-success d-flex align-items-center">
                                    <i class="feather-check-circle me-2 fs-4"></i>
                                    <div>
                                        <strong>2FA is active.</strong> You'll need a code from your authenticator app to sign in.
                                    </div>
                                </div>

                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('account.2fa.disable') }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                                <i class="feather-lock me-2"></i> Disable 2FA
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('account.2fa.recovery-codes') }}" class="d-inline">
                                            @csrf
                                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#recoveryCodesModal">
                                                <i class="feather-key me-2"></i> View Recovery Codes
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Disable 2FA Modal --}}
                            <div class="modal fade" id="disable2faModal" tabindex="-1" aria-labelledby="disable2faModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('account.2fa.disable') }}">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="disable2faModalLabel"><i class="feather-lock me-2"></i> Disable Two-Factor Authentication</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="feather-alert-triangle me-2"></i>
                                                    Disabling 2FA removes the extra layer of security from your account.
                                                </div>
                                                <p>Enter a code from your authenticator app (or a recovery code) and your password to confirm.</p>
                                                <div class="mb-3">
                                                    <label for="disable_code" class="form-label fw-semibold">6-Digit Code</label>
                                                    <input type="text" class="form-control form-control-lg text-center" id="disable_code" name="code" required maxlength="6" pattern="\d{6}" autocomplete="one-time-code" inputmode="numeric" placeholder="000000" style="letter-spacing: 0.5em;">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="disable_password" class="form-label fw-semibold">Current Password</label>
                                                    <input type="password" class="form-control form-control-lg" id="disable_password" name="current_password" required autocomplete="current-password">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Disable 2FA</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Recovery Codes Modal --}}
                            <div class="modal fade" id="recoveryCodesModal" tabindex="-1" aria-labelledby="recoveryCodesModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="recoveryCodesModalLabel"><i class="feather-key me-2"></i> Recovery Codes</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted mb-3">Your recovery codes (each can be used once):</p>
                                            @if (session('recovery_codes'))
                                                <div class="alert alert-info">
                                                    <strong>New recovery codes generated:</strong>
                                                </div>
                                            @endif
                                            <div class="row g-2">
                                                @foreach (auth()->user()->getRecoveryCodes() as $code)
                                                    <div class="col-6">
                                                        <code class="d-block bg-light p-3 text-center font-monospace fs-6 user-select-all">{{ $code }}</code>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-3">
                                                <form method="POST" action="{{ route('account.2fa.recovery-codes') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-warning w-100" onclick="return confirm('This will invalidate all current recovery codes and generate new ones. Continue?')">
                                                        <i class="feather-refresh-cw me-2"></i> Regenerate Recovery Codes
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @else
                            {{-- 2FA Disabled View --}}
                            <div class="rbt-service rbt-service-2 radius-10 mb--40 text-center py-5">
                                <div class="mb-4">
                                    <i class="feather-lock text-muted" style="font-size: 48px;"></i>
                                </div>
                                <h4 class="title mb-3">Two-Factor Authentication</h4>
                                <p class="text-muted mb-4">Add an extra layer of security to your account. When enabled, you'll need a code from your authenticator app to sign in.</p>
                                <a href="{{ route('account.2fa.show') }}" class="btn btn-primary btn-lg">
                                    <i class="feather-plus me-2"></i> Enable 2FA
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- ========== DANGER ZONE TAB ========== --}}
                    <div class="tab-pane fade" id="danger" role="tabpanel">
                        <div class="rbt-service rbt-service-2 radius-10 mb--40 border-danger">
                            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                                <h4 class="title mb-0 text-danger"><i class="feather-alert-triangle me-2"></i> Danger Zone</h4>
                            </div>

                            <div class="alert alert-warning mb-4">
                                <i class="feather-alert-circle me-2"></i>
                                <strong>Warning:</strong> These actions are irreversible. Please proceed with caution.
                            </div>

                            {{-- Delete Account --}}
                            <div class="p-4 border rounded bg-danger bg-opacity-10">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div>
                                        <h5 class="mb-1">Delete Your Account</h5>
                                        <p class="text-muted small mb-0">Permanently delete your account and all associated data. This cannot be undone.</p>
                                    </div>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                        <i class="feather-trash-2 me-2"></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Data Export --}}
                        <div class="rbt-service rbt-service-2 radius-10 bg-light">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h5 class="mb-1">Download Your Data</h5>
                                    <p class="text-muted small mb-0">Request a copy of your personal data and account activity.</p>
                                </div>
                                <button type="button" class="btn btn-outline-secondary" disabled>
                                    <i class="feather-download me-2"></i> Request Data Export
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

{{-- ========== MODALS ========== --}}

{{-- Add Email Modal --}}
<div class="modal fade" id="addEmailModal" tabindex="-1" aria-labelledby="addEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('account.emails.add') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmailModalLabel"><i class="feather-plus me-2"></i> Add Email Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">New Email Address</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" required autocomplete="email" placeholder="Enter email address">
                    </div>
                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current Password</label>
                        <input type="password" class="form-control form-control-lg" id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <p class="text-muted small">We'll send a verification link to this address.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Set Primary Modals --}}
@foreach ($emails as $email)
    @if ($email->is_verified && ! $email->is_primary)
        <div class="modal fade" id="setPrimaryModal{{ $email->id }}" tabindex="-1" aria-labelledby="setPrimaryModalLabel{{ $email->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('account.emails.primary', $email) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title" id="setPrimaryModalLabel{{ $email->id }}"><i class="feather-star me-2"></i> Set as Primary Email</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Make <strong>{{ $email->email }}</strong> your primary email address?</p>
                            <p class="text-muted small">This will be used for login and all notifications.</p>
                            <div class="mb-3">
                                <label for="current_password_primary{{ $email->id }}" class="form-label fw-semibold">Current Password</label>
                                <input type="password" class="form-control form-control-lg" id="current_password_primary{{ $email->id }}" name="current_password" required autocomplete="current-password">
                                @error('current_password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Set as Primary</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

{{-- Remove Email Modals --}}
@foreach ($emails as $email)
    @if (! $email->is_primary)
        <div class="modal fade" id="removeEmailModal{{ $email->id }}" tabindex="-1" aria-labelledby="removeEmailModalLabel{{ $email->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('account.emails.remove', $email) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h5 class="modal-title" id="removeEmailModalLabel{{ $email->id }}"><i class="feather-trash-2 me-2"></i> Remove Email</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to remove <strong>{{ $email->email }}</strong>?</p>
                            <p class="text-muted small">This action cannot be undone.</p>
                            <div class="mb-3">
                                <label for="current_password_remove{{ $email->id }}" class="form-label fw-semibold">Current Password</label>
                                <input type="password" class="form-control form-control-lg" id="current_password_remove{{ $email->id }}" name="current_password" required autocomplete="current-password">
                                @error('current_password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Remove Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

{{-- Delete Account Modal --}}
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAccountModalLabel"><i class="feather-alert-triangle me-2"></i> Delete Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="feather-alert-circle me-2"></i>
                    <strong>This action is permanent and cannot be undone.</strong>
                </div>
                <p>Deleting your account will:</p>
                <ul class="mb-4">
                    <li>Remove all your course progress and quiz attempts</li>
                    <li>Cancel any active subscriptions</li>
                    <li>Delete your purchase history</li>
                    <li>Remove all email addresses and personal data</li>
                </ul>
                <div class="mb-3">
                    <label for="delete_confirm" class="form-label fw-semibold">Type <code>DELETE</code> to confirm</label>
                    <input type="text" class="form-control form-control-lg" id="delete_confirm" placeholder="DELETE">
                    <div class="form-text">You must type DELETE in all caps to proceed.</div>
                </div>
                <div class="mb-3">
                    <label for="delete_password" class="form-label fw-semibold">Current Password</label>
                    <input type="password" class="form-control form-control-lg" id="delete_password" placeholder="Enter your password" autocomplete="current-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" disabled id="confirmDeleteBtn">
                    <i class="feather-trash-2 me-2"></i> Permanently Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Password Update Route (if not exists) --}}
@push('scripts')
<script>
    // Enable delete button only when "DELETE" is typed
    document.addEventListener('DOMContentLoaded', function() {
        const confirmInput = document.getElementById('delete_confirm');
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmInput && deleteBtn) {
            confirmInput.addEventListener('input', function() {
                deleteBtn.disabled = this.value !== 'DELETE';
            });
        }
    });
</script>
@endpush

@endsection