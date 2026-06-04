const TrackyValidate = {
  clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
  },
  showError(input, message) {
    input.classList.add('is-invalid');
    const fb = document.createElement('div');
    fb.className = 'invalid-feedback';
    fb.textContent = message;
    input.after(fb);
  },
  validatePhone(phone) {
    return /^(\+?6?01)[0-9\-]{8,10}$/.test(phone.replace(/\s/g, ''));
  },
  validateCheckout(form) {
    this.clearErrors(form);
    let ok = true;
    const name = form.querySelector('[name="customer_name"]');
    const phone = form.querySelector('[name="customer_phone"]');
    const address = form.querySelector('[name="delivery_address"]');
    if (!name.value.trim()) { this.showError(name, 'Nama diperlukan'); ok = false; }
    if (!phone.value.trim() || !this.validatePhone(phone.value)) {
      this.showError(phone, 'Format telefon tidak sah'); ok = false;
    }
    if (!address.value.trim()) { this.showError(address, 'Alamat diperlukan'); ok = false; }
    return ok;
  },
};
