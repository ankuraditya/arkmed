import { createContext,useCallback,useContext,useEffect,useRef,useState } from 'react'
import { adminApi,getAdminToken } from '../api/adminClient'
const AdminAuthContext=createContext(null)
export function AdminAuthProvider({children}){
  const [user,setUser]=useState(null);const [loading,setLoading]=useState(Boolean(getAdminToken()));const activity=useRef(0)
  const clearSession=useCallback(()=>{sessionStorage.removeItem('arkmed-admin-token');setUser(null)},[])
  const logout=useCallback(async()=>{try{if(getAdminToken())await adminApi.logout()}finally{clearSession();localStorage.setItem('arkmed-admin-logout',String(Date.now()))}},[clearSession])
  useEffect(()=>{activity.current=Date.now();if(!getAdminToken())return;adminApi.me().then(r=>setUser(r.data)).catch(clearSession).finally(()=>setLoading(false))},[clearSession])
  useEffect(()=>{const active=()=>{activity.current=Date.now()};const unauthorised=()=>clearSession();const sync=event=>{if(event.key==='arkmed-admin-logout')clearSession()};['pointerdown','keydown','touchstart'].forEach(name=>window.addEventListener(name,active,{passive:true}));window.addEventListener('arkmed-admin-unauthorised',unauthorised);window.addEventListener('storage',sync);const timer=setInterval(()=>{if(user&&Date.now()-activity.current>30*60*1000)logout()},60000);return()=>{['pointerdown','keydown','touchstart'].forEach(name=>window.removeEventListener(name,active));window.removeEventListener('arkmed-admin-unauthorised',unauthorised);window.removeEventListener('storage',sync);clearInterval(timer)}},[user,logout,clearSession])
  const login=async credentials=>{const result=await adminApi.login(credentials);sessionStorage.setItem('arkmed-admin-token',result.data.token);activity.current=Date.now();setUser(result.data.user)}
  return <AdminAuthContext.Provider value={{user,loading,login,logout,updateUser:setUser}}>{children}</AdminAuthContext.Provider>
}
// eslint-disable-next-line react-refresh/only-export-components
export const useAdminAuth=()=>useContext(AdminAuthContext)
