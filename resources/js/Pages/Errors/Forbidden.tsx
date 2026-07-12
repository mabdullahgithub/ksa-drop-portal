import ErrorStatusPage from './Error'

export default function Forbidden() {
  return <ErrorStatusPage status={403} />
}
