import { Link } from 'react-router-dom'
import { Heart, Pill } from 'lucide-react'
import { useCart } from '../context/CartContext'
import { useFavourites } from '../context/FavouritesContext'
import { stockLabels } from '../utils/medicine'
export default function MedicineCard({ medicine }) {
  const { add } = useCart()
  const {toggle,isFavourite}=useFavourites();const saved=isFavourite(medicine.id);const unavailable=medicine.stock==='out_of_stock'
  return <article className="medicine-card"><button className={`favourite-button ${saved?'saved':''}`} aria-label={saved?`Remove ${medicine.name} from favourites`:`Save ${medicine.name} to favourites`} aria-pressed={saved} onClick={()=>toggle(medicine)}><Heart/></button><Link className="medicine-image" to={`/medicines/${medicine.slug}`} aria-label={`View ${medicine.name}`}>{medicine.image?<img src={medicine.image} alt="" loading="lazy" width="260" height="190"/>:<Pill />}</Link><div className="card-badges"><span className={`stock-label ${medicine.stock}`}>{stockLabels[medicine.stock]||'Confirm availability'}</span>{medicine.prescription&&<span className="rx-label">Prescription required</span>}{medicine.discountPercent>0&&<span className="discount-label">{medicine.discountPercent}% off</span>}</div><h3><Link to={`/medicines/${medicine.slug}`}>{medicine.name}</Link></h3>{medicine.genericName&&<small className="generic-name">{medicine.genericName}</small>}<p>{medicine.detail}</p><div className="medicine-footer"><span><strong>{medicine.price!=null?`₹${Number(medicine.price).toFixed(2)}`:'Price on confirmation'}</strong>{medicine.mrp>medicine.price&&<del>₹{Number(medicine.mrp).toFixed(2)}</del>}</span><button disabled={unavailable} aria-label={unavailable?`${medicine.name} is unavailable`:`Add ${medicine.name} to cart`} onClick={()=>add(medicine)}>{unavailable?'Unavailable':'Add'}</button></div></article>
}
