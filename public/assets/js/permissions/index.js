(function() {
  var shell = document.querySelector('.permissions-shell');
  var input = document.getElementById('userSearchInput');
  var resultsDiv = document.getElementById('userSearchResults');
  var debounceTimer = null;
  var baseUrl = shell ? (shell.getAttribute('data-user-search-url') || '') : '';
  var permUrl = shell ? (shell.getAttribute('data-permission-url') || '') : '';

  function updatePermissionChoiceState(permissionCheckbox) {
    var choice = permissionCheckbox.closest('.permission-choice');
    if (!choice) {
      return;
    }

    var stateBadge = choice.querySelector('.permission-state-badge');
    if (stateBadge) {
      stateBadge.textContent = permissionCheckbox.checked ? 'Concedida' : 'Disponível';
      stateBadge.className = 'badge rounded-pill permission-state-badge ' + (permissionCheckbox.checked ? 'text-bg-success' : 'text-bg-light border');
    }

    var hint = choice.querySelector('.permission-choice-hint');
    if (hint) {
      hint.textContent = permissionCheckbox.checked ? 'Clique para remover' : 'Clique para conceder';
    }
  }

  function countChecked(checkboxes) {
    var checkedCount = 0;

    checkboxes.forEach(function(checkbox) {
      if (checkbox.checked) {
        checkedCount += 1;
      }
    });

    return checkedCount;
  }

  function getSystemPermissionCheckboxes(systemName) {
    return document.querySelectorAll('.permission-checkbox[data-permission-system="' + systemName + '"]');
  }

  function getGroupPermissionCheckboxes(systemName, groupName) {
    return document.querySelectorAll('.permission-checkbox[data-permission-system="' + systemName + '"][data-permission-group="' + groupName + '"]');
  }

  function syncBulkToggleState(toggleCheckbox, permissionCheckboxes) {
    if (!toggleCheckbox) {
      return;
    }

    if (permissionCheckboxes.length === 0) {
      toggleCheckbox.checked = false;
      toggleCheckbox.indeterminate = false;
      toggleCheckbox.disabled = true;
      return;
    }

    var checkedCount = countChecked(permissionCheckboxes);
    toggleCheckbox.disabled = false;
    toggleCheckbox.checked = checkedCount === permissionCheckboxes.length;
    toggleCheckbox.indeterminate = checkedCount > 0 && checkedCount < permissionCheckboxes.length;
  }

  function refreshSelectionMetrics() {
    var permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    var groupToggleCheckboxes = document.querySelectorAll('.group-toggle-checkbox');
    var systemToggleCheckboxes = document.querySelectorAll('.system-toggle-checkbox');
    var totalGrantedCount = countChecked(permissionCheckboxes);

    permissionCheckboxes.forEach(function(permissionCheckbox) {
      updatePermissionChoiceState(permissionCheckbox);
    });

    groupToggleCheckboxes.forEach(function(groupToggle) {
      var systemName = groupToggle.getAttribute('data-system-toggle');
      var groupName = groupToggle.getAttribute('data-group-toggle');
      var groupKey = systemName + '::' + groupName;
      var groupCheckboxes = getGroupPermissionCheckboxes(systemName, groupName);
      var checkedCount = countChecked(groupCheckboxes);
      var groupCountTargets = document.querySelectorAll('[data-group-selected-count="' + groupKey + '"]');

      groupCountTargets.forEach(function(target) {
        target.textContent = checkedCount;
      });

      syncBulkToggleState(groupToggle, groupCheckboxes);
    });

    systemToggleCheckboxes.forEach(function(systemToggle) {
      var systemName = systemToggle.getAttribute('data-system-toggle');
      var systemCheckboxes = getSystemPermissionCheckboxes(systemName);
      var checkedCount = countChecked(systemCheckboxes);
      var systemCountTargets = document.querySelectorAll('[data-system-selected-count="' + systemName + '"]');

      systemCountTargets.forEach(function(target) {
        target.textContent = checkedCount;
      });

      syncBulkToggleState(systemToggle, systemCheckboxes);
    });

    document.querySelectorAll('[data-total-granted-count]').forEach(function(target) {
      target.textContent = totalGrantedCount;
    });
  }

  function setAccordionState(expand) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
      return;
    }

    document.querySelectorAll('.permission-accordion .accordion-collapse').forEach(function(collapseElement) {
      var collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });

      if (expand) {
        collapseInstance.show();
        return;
      }

      collapseInstance.hide();
    });
  }

  if (input && resultsDiv) {
    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      var q = input.value.trim();

      if (q.length < 2) {
        resultsDiv.style.display = 'none';
        resultsDiv.innerHTML = '';
        return;
      }

      debounceTimer = setTimeout(function() {
        fetch(baseUrl + '?q=' + encodeURIComponent(q), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function(r) { return r.json(); })
          .then(function(users) {
            resultsDiv.innerHTML = '';

            if (users.length === 0) {
              resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nenhum usuário encontrado.</div>';
              resultsDiv.style.display = 'block';
              return;
            }

            users.forEach(function(u) {
              var item = document.createElement('a');
              item.href = permUrl + '?user_id=' + u.id;
              item.className = 'list-group-item list-group-item-action py-2';

              var nameSpan = document.createElement('span');
              nameSpan.className = 'fw-semibold';
              nameSpan.textContent = u.name || u.username;

              var badge = document.createElement('span');
              badge.className = 'badge text-bg-info ms-2';
              badge.textContent = u.type;

              var emailDiv = document.createElement('div');
              emailDiv.className = 'small text-muted';
              emailDiv.textContent = u.email;

              if (u.username && u.username !== (u.name || '')) {
                emailDiv.textContent += ' (' + u.username + ')';
              }

              item.appendChild(nameSpan);
              item.appendChild(badge);
              item.appendChild(emailDiv);
              resultsDiv.appendChild(item);
            });

            resultsDiv.style.display = 'block';
          })
          .catch(function() {
            resultsDiv.style.display = 'none';
          });
      }, 300);
    });

    input.addEventListener('focus', function() {
      if (resultsDiv.innerHTML.trim() !== '') {
        resultsDiv.style.display = 'block';
      }
    });
  }

  document.addEventListener('click', function(e) {
    var wrapper = document.getElementById('userSearchWrapper');
    if (wrapper && resultsDiv && !wrapper.contains(e.target)) {
      resultsDiv.style.display = 'none';
    }
  });

  var toggleOtherSystem = document.getElementById('toggleOtherSystem');
  if (toggleOtherSystem) {
    var userOrigin = shell ? (shell.getAttribute('data-user-origin-system') || '') : '';
    toggleOtherSystem.addEventListener('change', function() {
      document.querySelectorAll('.perm-system-block').forEach(function(block) {
        var systemName = block.getAttribute('data-system');

        if (systemName === userOrigin) {
          block.style.display = '';
          return;
        }

        block.style.display = toggleOtherSystem.checked ? '' : 'none';
      });
    });
  }

  document.querySelectorAll('.system-toggle-checkbox').forEach(function(systemToggle) {
    systemToggle.addEventListener('change', function() {
      var systemName = systemToggle.getAttribute('data-system-toggle');
      var permissionCheckboxes = getSystemPermissionCheckboxes(systemName);

      permissionCheckboxes.forEach(function(permissionCheckbox) {
        permissionCheckbox.checked = systemToggle.checked;
      });

      systemToggle.indeterminate = false;
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('.group-toggle-checkbox').forEach(function(groupToggle) {
    groupToggle.addEventListener('change', function() {
      var systemName = groupToggle.getAttribute('data-system-toggle');
      var groupName = groupToggle.getAttribute('data-group-toggle');
      var permissionCheckboxes = getGroupPermissionCheckboxes(systemName, groupName);

      permissionCheckboxes.forEach(function(permissionCheckbox) {
        permissionCheckbox.checked = groupToggle.checked;
      });

      groupToggle.indeterminate = false;
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('.permission-checkbox').forEach(function(permissionCheckbox) {
    permissionCheckbox.addEventListener('change', function() {
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('[data-permission-accordion-action]').forEach(function(actionButton) {
    actionButton.addEventListener('click', function() {
      var action = actionButton.getAttribute('data-permission-accordion-action');
      setAccordionState(action === 'expand');
    });
  });

  refreshSelectionMetrics();
})();
