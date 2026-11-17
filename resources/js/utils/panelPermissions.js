// resources/js/utils/panelPermissions.js

export function resolvePanelFieldState(
  field,
  { isServerAdmin = false, isConsoAdmin = false, canEditOrg = false } = {}
) {
  // 1. Visibility: hide invisible fields and ["Add"]-only fields
  if (field.visible === false) {
    return { visible: false, editable: false };
  }
  if (Array.isArray(field.visible) && !field.visible.includes("Edit")) {
    return { visible: false, editable: false };
  }

  // 2. Non-admins are strictly view-only across the entire org panel
  if (!canEditOrg) {
    return { visible: true, editable: false };
  }

  // 3. Static schema locks (e.g., editable: false or editable: ["Add"] on ccp_key)
  if (field.editable === false) {
    return { visible: true, editable: false };
  }
  if (Array.isArray(field.editable) && !field.editable.includes("Edit")) {
    return { visible: true, editable: false };
  }

  // 4. Role-specific locks (e.g., editableBy: ["ConsoAdmin", "ServerAdmin"])
  if (Array.isArray(field.editableBy)) {
    const allowed = field.editableBy.some((role) => {
      if (role === "ServerAdmin") return isServerAdmin;
      if (role === "ConsoAdmin") return isConsoAdmin || isServerAdmin;
      return false;
    });
    return { visible: true, editable: allowed };
  }

  return { visible: true, editable: true };
}
