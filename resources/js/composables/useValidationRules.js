// composables/useValidationRules.js
export function useValidationRules() {
  const required = (value) => !!value || 'Required';

  const numberRule = (value) =>
    !isNaN(Number(value)) || 'Must be a number';

  const emailRule = (value) =>
    !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Invalid email';

  const booleanRule = (value) =>
    (typeof(value) === 'boolean') ? true : 'Must be true or false';

  const yearmon = (value) => {
    if (!value) return 'This field is required';
    const regex = /^\d{4}-(0[1-9]|1[0-2])$/;
    return regex.test(value) || 'Enter a valid year and month (YYYY-MM)';
  };

  return { required, numberRule, emailRule, booleanRule, yearmon };
}