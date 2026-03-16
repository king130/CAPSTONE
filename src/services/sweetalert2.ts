import SwalBase from 'sweetalert2/dist/sweetalert2.esm.js'

const Swal = SwalBase.mixin({
  buttonsStyling: false,
  reverseButtons: true,
  focusConfirm: false,
  showClass: {
    popup: 'capstone-swal-show',
    backdrop: 'capstone-swal-backdrop',
  },
  hideClass: {
    popup: 'capstone-swal-hide',
  },
  customClass: {
    popup: 'capstone-swal-popup',
    title: 'capstone-swal-title',
    htmlContainer: 'capstone-swal-text',
    confirmButton: 'capstone-swal-confirm',
    cancelButton: 'capstone-swal-cancel',
    denyButton: 'capstone-swal-deny',
    actions: 'capstone-swal-actions',
    input: 'capstone-swal-input',
    select: 'capstone-swal-select',
  },
})

export default Swal
export { Swal }
