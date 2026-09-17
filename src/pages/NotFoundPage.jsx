import { Link } from 'react-router-dom'
import Seo from '../components/Seo'
export default function NotFoundPage(){return <div className="page content-width empty-state"><Seo title="Page not found" noIndex/><span className="kicker">404</span><h1>Page not found</h1><p>The page you requested does not exist or may have moved.</p><div className="form-actions"><Link className="button primary" to="/">Return home</Link><Link className="button secondary" to="/medicines">Browse medicines</Link></div></div>}
