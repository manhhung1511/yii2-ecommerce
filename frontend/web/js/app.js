

 $(function(){
   const $cartQuantity = $('#cart-quantity');
  //  console.log($cartQuantity);
   const $addToCart = $('.btn-add-to-cart');
   const $itemQuantities = $('.item-quantity');
  //  console.log($itemQuantities);
   $addToCart.click(ev => {
     ev.preventDefault();
     const $this = $(ev.target);
     const id = $this.closest('.product-item').data('key');
     console.log(id);
     $.ajax({
       method: 'POST',
       url: $this.attr('href'),
       data: {id},
       success: function(){
         console.log(arguments)
         $cartQuantity.text(parseInt($cartQuantity.text() || 0) + 1);
       }
     })
   })
 
   $itemQuantities.click(ev => {
     const $this = $(ev.target);
     let $tr = $this.closest('tr');
     const $td = $this.closest('td');
     const id = $tr.data('id');
 
     $.ajax({
       method: 'post',
       url: $tr.data('url'),
       data: {id, quantity: $this.val()},
       success: function(result){
         console.log(arguments);
         $cartQuantity.text(result.quantity);
         $td.next().text(result.price);
       }
     })
   })
 });