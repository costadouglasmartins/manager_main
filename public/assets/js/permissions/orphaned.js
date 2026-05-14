(function() {
  var deleteModal = document.getElementById('deleteModal');

  if (!deleteModal) {
    return;
  }

  deleteModal.addEventListener('show.bs.modal', function(e) {
    var trigger = e.relatedTarget;
    var deleteForm = document.getElementById('deleteForm');
    var abilityName = document.getElementById('abilityName');
    var affectedSection = document.getElementById('affectedUsersSection');
    var noUsersSection = document.getElementById('noUsersSection');
    var userCountText = document.getElementById('userCountText');
    var affectedUsersList = document.getElementById('affectedUsersList');

    if (!trigger || !deleteForm || !abilityName || !affectedSection || !noUsersSection) {
      return;
    }

    var permId = trigger.dataset.id || '';
    var ability = trigger.dataset.ability || '';
    var userCount = parseInt(trigger.dataset.userCount, 10) || 0;
    var deleteUrlBase = (deleteForm.dataset.deleteUrlBase || '').replace(/\/$/, '');

    abilityName.textContent = ability;
    deleteForm.action = deleteUrlBase + '/' + encodeURIComponent(permId);

    if (userCount > 0) {
      affectedSection.classList.remove('d-none');
      noUsersSection.classList.add('d-none');

      if (userCountText) {
        userCountText.textContent = userCount + ' usuário(s)';
      }

      if (affectedUsersList) {
        affectedUsersList.textContent = 'A remoção apagará os vínculos dessa permissão para todos esses usuários.';
      }

      return;
    }

    affectedSection.classList.add('d-none');
    noUsersSection.classList.remove('d-none');

    if (userCountText) {
      userCountText.textContent = '';
    }

    if (affectedUsersList) {
      affectedUsersList.textContent = '';
    }
  });
})();
