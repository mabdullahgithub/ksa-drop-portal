import ErrorStatusPage from './Error'

export default function InternalServerError() {
  return <ErrorStatusPage status={500} />
}
