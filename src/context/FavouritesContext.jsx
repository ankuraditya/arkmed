import { createContext,useContext,useEffect,useMemo,useState } from 'react'
const FavouritesContext=createContext(null);const KEY='arkmed-favourites-v1'
function read(){try{const value=JSON.parse(localStorage.getItem(KEY));return Array.isArray(value)?value.filter(item=>item?.id&&item?.name).slice(0,50):[]}catch{return[]}}
export function FavouritesProvider({children}){const [items,setItems]=useState(read);useEffect(()=>localStorage.setItem(KEY,JSON.stringify(items)),[items]);useEffect(()=>{const sync=event=>{if(event.key===KEY)setItems(read())};addEventListener('storage',sync);return()=>removeEventListener('storage',sync)},[]);const value=useMemo(()=>({items,isFavourite:id=>items.some(item=>item.id===id),toggle:medicine=>setItems(current=>current.some(item=>item.id===medicine.id)?current.filter(item=>item.id!==medicine.id):[{...medicine},...current].slice(0,50)),clear:()=>setItems([])}),[items]);return <FavouritesContext.Provider value={value}>{children}</FavouritesContext.Provider>}
// eslint-disable-next-line react-refresh/only-export-components
export const useFavourites=()=>useContext(FavouritesContext)
