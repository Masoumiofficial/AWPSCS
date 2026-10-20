// k-means سبک برای پیشنهاد تیره پویا (3-10) — elbow method
export type Point = number // views
export function kmeans(points: number[], k: number, iter=20): {centroids:number[], clusters:number[][]} {
  if (points.length===0) return {centroids:[], clusters:[]}
  let centroids = points.slice(0,k).sort((a,b)=>a-b)
  if (centroids.length < k) centroids = [...centroids, ...Array(k-centroids.length).fill(Math.max(...points))]
  let clusters: number[][] = []
  for (let i=0;i<iter;i++) {
    clusters = Array.from({length:k},()=>[])
    for (const p of points) {
      let best=0, bestd=Infinity
      centroids.forEach((c,idx)=>{ const d=Math.abs(p-c); if(d<bestd){bestd=d; best=idx} })
      clusters[best].push(p)
    }
    const next = clusters.map(c=> c.length? c.reduce((a,b)=>a+b,0)/c.length : 0)
    if (next.every((v,i)=>Math.abs(v-centroids[i])<1)) break
    centroids = next
  }
  return {centroids: centroids.sort((a,b)=>a-b), clusters}
}
export function elbow(points: number[]): number {
  // WCSS برای k=3..10 → بیشترین افت
  let prev = Infinity, bestK=7, bestDrop=0
  for (let k=3;k<=10;k++) {
    const {centroids, clusters} = kmeans(points,k)
    let wcss=0; clusters.forEach((cl,idx)=>{ const c=centroids[idx]; cl.forEach(p=> wcss+=(p-c)**2)})
    const drop = prev - wcss
    if (drop > bestDrop) { bestDrop=drop; bestK=k }
    prev = wcss
  }
  return bestK
}
