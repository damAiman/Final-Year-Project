/* SMART STOCK -- User Management modals */
document.addEventListener('DOMContentLoaded', function () {
  var userModalEl = document.getElementById('userModal');
  if (!userModalEl) return;

  var userModal  = new bootstrap.Modal(userModalEl);
  var resetModal = new bootstrap.Modal(document.getElementById('resetModal'));

  var f = {
    id:       document.getElementById('u-id'),
    name:     document.getElementById('u-name'),
    username: document.getElementById('u-username'),
    email:    document.getElementById('u-email'),
    role:     document.getElementById('u-role'),
    password: document.getElementById('u-password')
  };
  var title  = document.getElementById('userModalTitle');
  var pwHint = document.getElementById('pwHint');
  var pwStar = document.getElementById('pwStar');

  document.getElementById('btnAddUser').addEventListener('click', function () {
    document.getElementById('userForm').reset();
    f.id.value = '';
    title.textContent = 'Add User';
    f.password.required = true;
    pwStar.style.display = '';
    pwHint.textContent = 'Minimum 6 characters. The user can change it later.';
    userModal.show();
  });

  document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
    btn.addEventListener('click', function () {
      f.id.value       = btn.dataset.id;
      f.name.value     = btn.dataset.name || '';
      f.username.value = btn.dataset.username || '';
      f.email.value    = btn.dataset.email || '';
      f.role.value     = btn.dataset.role || 'staff';
      f.password.value = '';
      f.password.required = false;
      pwStar.style.display = 'none';
      pwHint.textContent = 'Leave blank to keep the current password.';
      title.textContent = 'Edit User';
      userModal.show();
    });
  });

  document.querySelectorAll('.btn-reset-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('r-id').value = btn.dataset.id;
      document.getElementById('r-name').textContent = btn.dataset.name || 'this user';
      document.getElementById('r-password').value = '';
      resetModal.show();
    });
  });
});
