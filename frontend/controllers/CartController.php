<?php
namespace frontend\controllers;

use common\models\CartItem;
use common\models\Product;
use Yii;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use frontend\base\Controller;
use yii\web\NotFoundHttpException;
use common\models\Order;
use common\models\OrderAddress;

    class CartController extends Controller
    {
        public function behaviors()
        {
            return [
                [
                    'class'=>ContentNegotiator::class,
                    'only' => ['add','change-quantity'],
                    'formats' => [
                        'application/json' => Response::FORMAT_JSON,
                    ],
                ],
            ];
        }
        public function actionIndex()
        {
            $cartItems = CartItem::getItemsForUser(currUserId());
            return $this->render('index', [
                'items' => $cartItems
            ]);
        }

        public function actionAdd()
        {
            $id = Yii::$app->request->post('id');
            $product = Product::find()->id($id)->published()->one();
            
            if(Yii::$app->user->isGuest) 
            {
                $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);

                $found = false;
                foreach($cartItems as &$item) {
                    if($item['id'] == $id) {
                        $item['quantity']++;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $cartItem = [
                        'id' => $id,
                        'name' => $product->name,
                        'image' => $product->image,
                        'price' => $product->price,
                        'quantity' => 1,
                        'total_price' => $product->price
                    ];
                    $cartItems[] = $cartItem;
                }
                Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);

            } else {
                $userId = currUserId();
                $cartItem = CartItem::find()->userId($userId)->productId($id)->one();
                if($cartItem) {
                    $cartItem->quantity++;
                } else {
                    $cartItem = new CartItem();
                    $cartItem->product_id = $id;
                    $cartItem->created_by = $userId;
                    $cartItem->quantity = 1;
                }
                if($cartItem->save()) {
                    return [
                        'success'=>true
                    ];
                } else {
                    return [
                        'success'=>false,
                        'errors'=> $cartItem->errors
                    ];
                }
            }
        }

        public function actionDelete($id) 
        {
            if(Yii::$app->user->isGuest) {
                $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
                foreach($cartItems as $cartItem) {
                    if($cartItem['id'] == $id) {
                        array_splice($cartItem,$id,1);
                        break;
                    }
                }
                Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
            } else {
                CartItem::deleteAll(['product_id'=>$id, 'created_by'=>currUserId()]);
            }
            return $this->redirect(['index']);
        }

        public function actionChangeQuantity()
        {
            $id = Yii::$app->request->post('id');
            $product = Product::find()->id($id)->published()->one();
            if(!$product) {
                throw new NotFoundHttpException("Product does not exit");
            }
            $quantity = Yii::$app->request->post('quantity');
            if(Yii::$app->user->isGuest) {
                $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
                foreach($cartItems as &$cartItem) {
                    if($cartItem['id'] === $id) {
                        $cartItem['quantity'] = $quantity;
                        break;
                    }
                }
                Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
            } else {
                $cartItem = CartItem::find()->userId(currUserId())->productId($id)->one();
                if ($cartItem) {
                    $cartItem->quantity = $quantity;
                    $cartItem->save();
                }
            }
            return [
                 'quantity' => CartItem::getTotalQuantityForUser(currUserId()),
                 'price' => Yii::$app->formatter->asCurrency(CartItem::getTotalPriceForItemForUser($id, currUserId()))
            ];
        }

        public function actionCheckout()
        {
            $cartItems = CartItem::getItemsForUser(currUserId());
            $productQuantity = CartItem::getTotalQuantityForUser(currUserId());
            $totalPrice = CartItem::getTotalPriceForUser(currUserId());
            if(empty($cartItems)) {
                return $this->redirect([Yii::$app->homeUrl]);
            }
            $order = new Order();
            $order->total_price = $totalPrice;
            $order->status = Order::STATUS_DRAFT;
            $order->created_at = time();
            $order->created_by = currUserId();
            $orderAddress = new OrderAddress();
            if(!isGuest()) {
                /** @var \common\models\User $user */
                $user = Yii::$app->user->identity;
               
                $userAddress = $user->getAddress();
                $order->firstname = $user->firstname;
                $order->lastname = $user->lastname;
                $order->email = $user->email;
                $order->status = Order::STATUS_DRAFT;

                $orderAddress->address = $userAddress->address;
                $orderAddress->city = $userAddress->city;
                $orderAddress->state = $userAddress->state;
                $orderAddress->country = $userAddress->country;
                $orderAddress->zipcode = $orderAddress->zipcode;
             }
            return $this->render('checkout',[
                'order' => $order,
                'productQuantity' => $productQuantity,
                'cartItems' => $cartItems,
                'orderAddress' => $orderAddress,
                'totalPrice' => $totalPrice
            ]);
        }
    }

?>