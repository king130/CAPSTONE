/**
 * Canonical agreements service entrypoint.
 * Re-exports the shared implementation used by school/company agreement UIs.
 */
export {
  type ContractRecord as AgreementRecord,
  type ContractRecord,
  acceptContract as acceptAgreement,
  rejectContract as rejectAgreement,
  cancelContract as cancelAgreement,
  amendContract as amendAgreement,
  createContractRequest as createAgreementRequest,
  subscribeCompanyContracts as subscribeCompanyAgreements,
  subscribeSchoolContracts as subscribeSchoolAgreements,
  acceptContract,
  rejectContract,
  cancelContract,
  amendContract,
  createContractRequest,
  subscribeCompanyContracts,
  subscribeSchoolContracts,
} from './contracts'
