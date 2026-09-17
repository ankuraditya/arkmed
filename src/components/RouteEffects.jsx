import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
export default function RouteEffects(){const {pathname}=useLocation();useEffect(()=>{window.scrollTo(0,0);document.querySelector('main')?.focus({preventScroll:true});if(pathname.startsWith('/admin')){document.title='Secure administration | ARK med';let robots=document.head.querySelector('meta[name="robots"]');if(!robots){robots=document.createElement('meta');robots.name='robots';document.head.appendChild(robots)}robots.content='noindex, nofollow'}},[pathname]);return null}
