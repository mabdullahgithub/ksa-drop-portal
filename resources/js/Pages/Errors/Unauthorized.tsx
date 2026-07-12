import ErrorStatusPage from './Error'

export default function Unauthorized() {
  return <ErrorStatusPage status={401} />
}
