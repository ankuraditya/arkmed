import { Navigate,Outlet } from 'react-router-dom'
import { useAdminAuth } from '../context/AdminAuthContext'
export default function AdminRoute(){const {user,loading}=useAdminAuth();if(loading)return <div className="admin-loading">Loading admin…</div>;return user?<Outlet/>:<Navigate to="/admin/login" replace/>}
